<?php

namespace App\Services\Google\Vertex;

use App\Models\VertexOcrResult;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class VertexDetectService
{
    private const DEFAULT_FEATURE_TYPE = 'DOCUMENT_TEXT_DETECTION';

    private const TEXT_FEATURE_TYPES = ['TEXT_DETECTION', 'DOCUMENT_TEXT_DETECTION'];

    private const OBJECT_FEATURE_TYPE = 'OBJECT_LOCALIZATION';

    public function detect(array $validated): array
    {
        $vertexAiConfig = (array) config('services.vertex_ai', []);
        $credentialsPath = (string) ($vertexAiConfig['credentials_path'] ?? '');

        if ($credentialsPath === '') {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS is not configured.');
        }

        if (! is_file($credentialsPath)) {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS file was not found.');
        }

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $imageBytes = file_get_contents($image->getRealPath());

        if ($imageBytes === false) {
            throw new RuntimeException('Failed to read image bytes.');
        }

        $credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/cloud-platform'],
            $credentialsPath,
        );
        $tokenData = $credentials->fetchAuthToken();
        $accessToken = (string) ($tokenData['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Unable to fetch Google access token for Cloud Vision API.');
        }

        $featureTypes = collect($validated['types'] ?? [self::DEFAULT_FEATURE_TYPE])
            ->filter(fn ($type) => is_string($type) && $type !== '')
            ->unique()
            ->values()
            ->all();

        if ($featureTypes === []) {
            $featureTypes = [self::DEFAULT_FEATURE_TYPE];
        }

        $features = collect($featureTypes)
            ->map(fn (string $type): array => ['type' => $type])
            ->all();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://vision.googleapis.com/v1/images:annotate', [
                'requests' => [
                    [
                        'image' => [
                            'content' => base64_encode($imageBytes),
                        ],
                        'features' => $features,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error.message', 'Cloud Vision API request failed.');
            throw new RuntimeException($errorMessage);
        }

        $ocrPayload = (array) data_get($response->json(), 'responses.0', []);
        $ocrError = (string) ($ocrPayload['error']['message'] ?? '');

        if ($ocrError !== '') {
            throw new RuntimeException($ocrError);
        }

        $hasTextFeature = count(array_intersect($featureTypes, self::TEXT_FEATURE_TYPES)) > 0;
        $hasObjectFeature = in_array(self::OBJECT_FEATURE_TYPE, $featureTypes, true);

        $fullText = null;
        $pageCount = null;
        $textBlocks = null;

        if ($hasTextFeature) {
            $detectedText = (string) (
                data_get($ocrPayload, 'fullTextAnnotation.text')
                ?? data_get($ocrPayload, 'textAnnotations.0.description')
                ?? ''
            );

            $fullText = $detectedText === '' ? null : $detectedText;
            $pageCount = count((array) data_get($ocrPayload, 'fullTextAnnotation.pages', []));
            $textBlocks = count((array) data_get($ocrPayload, 'textAnnotations', []));
        }

        $localizedObjects = [];

        if ($hasObjectFeature) {
            $localizedObjects = collect((array) data_get($ocrPayload, 'localizedObjectAnnotations', []))
                ->map(function (array $annotation): array {
                    $normalizedVertices = collect((array) data_get($annotation, 'boundingPoly.normalizedVertices', []))
                        ->map(function (array $vertex): array {
                            return [
                                'x' => (float) ($vertex['x'] ?? 0),
                                'y' => (float) ($vertex['y'] ?? 0),
                            ];
                        })
                        ->values()
                        ->all();

                    return [
                        'name' => (string) ($annotation['name'] ?? 'unknown'),
                        'score' => (float) ($annotation['score'] ?? 0),
                        'bounding_poly' => $normalizedVertices,
                    ];
                })
                ->values()
                ->all();
        }

        if ($fullText === null && $localizedObjects === []) {
            throw new RuntimeException('Cloud Vision detect returned an empty response.');
        }

        $storedPath = $this->storeImage($image);

        $record = VertexOcrResult::query()->create([
            'image_name' => $image->getClientOriginalName(),
            'image_path' => $storedPath,
            'mime_type' => $image->getMimeType(),
            'image_size' => $image->getSize(),
            'types' => $featureTypes,
            'text' => $fullText ?? '',
            'provider' => 'cloud_vision_ocr',
            'raw_response' => $ocrPayload,
        ]);

        return [
            'text' => $fullText,
            'page_count' => $pageCount,
            'text_block_count' => $textBlocks,
            'objects' => $localizedObjects,
            'object_count' => count($localizedObjects),
            'provider' => 'cloud_vision_ocr',
            'types' => $featureTypes,
            'raw_response' => $ocrPayload,
            'record' => [
                'id' => $record->id,
                'image_name' => $record->image_name,
                'image_path' => $record->image_path,
                'image_url' => asset('storage/'.$record->image_path),
                'created_at' => $record->created_at?->toISOString(),
            ],
        ];
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $safeExtension = $extension !== '' ? strtolower($extension) : 'bin';
        $fileName = sprintf('%s_%s.%s', now()->format('Ymd_His'), Str::uuid(), $safeExtension);
        $storedPath = $image->storeAs('vertex-ocr-images', $fileName, 'public');

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('Failed to store OCR image file.');
        }

        return $storedPath;
    }
}
