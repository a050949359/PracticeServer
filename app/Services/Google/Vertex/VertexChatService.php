<?php

namespace App\Services\Google\Vertex;

use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\GenerateContentRequest;
use Google\Cloud\AIPlatform\V1\Part;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class VertexChatService
{
    public function ask(array $validated): array
    {
        $vertexAiConfig = (array) config('services.vertex_ai', []);
        $credentialsPath = (string) ($vertexAiConfig['credentials_path'] ?? '');
        $projectId = (string) ($vertexAiConfig['project_id'] ?? '');
        $location = (string) ($vertexAiConfig['location'] ?? 'us-central1');
        $model = (string) ($vertexAiConfig['model'] ?? 'gemini-2.0-flash-001');

        if ($projectId === '') {
            throw new RuntimeException('VERTEX_AI_PROJECT_ID is not configured.');
        }

        if ($credentialsPath === '') {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS is not configured.');
        }

        if (! is_file($credentialsPath)) {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS file was not found.');
        }

        $client = new PredictionServiceClient([
            'apiEndpoint' => sprintf('%s-aiplatform.googleapis.com', $location),
            'credentials' => $credentialsPath,
            'transport' => 'rest',
        ]);

        try {
            $response = $client->generateContent(new GenerateContentRequest([
                'model' => PredictionServiceClient::projectLocationPublisherModelName(
                    $projectId,
                    $location,
                    'google',
                    $model,
                ),
                'contents' => $this->buildContents($validated),
            ]));
        } catch (Throwable $throwable) {
            throw new RuntimeException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        } finally {
            $client->close();
        }

        $candidate = $response->getCandidates()[0] ?? null;
        $parts = $candidate?->getContent()?->getParts() ?? [];
        $reply = collect(iterator_to_array($parts))
            ->map(fn (Part $part) => $part->getText())
            ->filter()
            ->implode("\n");

        if ($reply === '') {
            throw new RuntimeException('Vertex AI returned an empty response.');
        }

        $usageMetadata = $response->getUsageMetadata();

        return [
            'reply' => $reply,
            'model' => $model,
            'response_id' => $response->getResponseId(),
            'usage' => [
                'prompt_token_count' => $usageMetadata?->getPromptTokenCount(),
                'candidates_token_count' => $usageMetadata?->getCandidatesTokenCount(),
                'total_token_count' => $usageMetadata?->getTotalTokenCount(),
            ],
        ];
    }

    private function buildContents(array $validated): array
    {
        $history = collect(Arr::get($validated, 'messages', []))
            ->map(fn (array $message) => new Content([
                'role' => $message['role'],
                'parts' => [
                    new Part([
                        'text' => $message['content'],
                    ]),
                ],
            ]))
            ->all();

        $history[] = new Content([
            'role' => 'user',
            'parts' => [
                new Part([
                    'text' => $validated['prompt'],
                ]),
            ],
        ]);

        return $history;
    }
}
