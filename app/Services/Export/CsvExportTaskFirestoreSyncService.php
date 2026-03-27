<?php

namespace App\Services\Export;

use App\Models\CsvExportTask;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CsvExportTaskFirestoreSyncService
{
    private ?ServiceAccountCredentials $credentials = null;

    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function syncTask(CsvExportTask $task): void
    {
        if (! $this->isSyncEnabled()) {
            return;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->patch($this->documentUrl((string) $task->id), [
                    'fields' => $this->encodeMapFields($this->payload($task)),
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Firestore API request failed: '.$response->status().' '.$response->body());
            }

            Log::info('CSV export task synced to Firestore.', [
                'task_id' => $task->id,
                'project_id' => (string) config('services.firestore.project_id', ''),
                'collection' => $this->collectionName(),
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Failed to sync CSV export task to Firestore.', [
                'task_id' => $task->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function documentUrl(string $documentId): string
    {
        $projectId = trim((string) config('services.firestore.project_id', ''));

        if ($projectId === '') {
            throw new RuntimeException('FIRESTORE_PROJECT_ID is required to call Firestore API.');
        }

        $database = trim((string) config('services.firestore.database', '(default)'));

        if ($database === '' || $database === 'default') {
            $database = '(default)';
        }

        $collectionPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            $this->pathSegments($this->collectionName()),
        ));

        $encodedDocumentId = rawurlencode($documentId);

        return sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/%s/documents/%s/%s',
            rawurlencode($projectId),
            rawurlencode($database),
            $collectionPath,
            $encodedDocumentId,
        );
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $tokenData = $this->credentials()->fetchAuthToken();
        $token = $tokenData['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Unable to fetch Firestore access token from service account credentials.');
        }

        $this->accessToken = $token;

        $expiresAt = $tokenData['expires_at'] ?? null;
        if (is_numeric($expiresAt)) {
            $this->accessTokenExpiresAt = (int) $expiresAt;
        } else {
            $expiresIn = $tokenData['expires_in'] ?? 3600;
            $this->accessTokenExpiresAt = time() + (int) $expiresIn;
        }

        return $this->accessToken;
    }

    private function credentials(): ServiceAccountCredentials
    {
        if ($this->credentials instanceof ServiceAccountCredentials) {
            return $this->credentials;
        }

        $credentialsPath = trim((string) config('services.firestore.credentials_path', ''));

        if ($credentialsPath === '') {
            throw new RuntimeException('FIRESTORE_CREDENTIALS is required to call Firestore API.');
        }

        if (! is_file($credentialsPath) || ! is_readable($credentialsPath)) {
            throw new RuntimeException('FIRESTORE_CREDENTIALS file is not readable: '.$credentialsPath);
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);

        if (! is_array($json)) {
            throw new RuntimeException('FIRESTORE_CREDENTIALS must be a valid service account JSON file.');
        }

        $this->credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/datastore'],
            $json,
        );

        Log::info('Firestore REST credentials initialized.', [
            'project_id' => (string) config('services.firestore.project_id', ''),
            'collection' => $this->collectionName(),
        ]);

        return $this->credentials;
    }

    private function collectionName(): string
    {
        return (string) config('services.firestore.task_collection', 'csv_export_tasks');
    }

    private function isSyncEnabled(): bool
    {
        return (bool) config('services.firestore.sync_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    private function encodeMapFields(array $values): array
    {
        $fields = [];

        foreach ($values as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeValue(mixed $value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_array($value)) {
            if ($this->isListArray($value)) {
                return [
                    'arrayValue' => [
                        'values' => array_map(fn (mixed $item): array => $this->encodeValue($item), $value),
                    ],
                ];
            }

            return [
                'mapValue' => [
                    'fields' => $this->encodeMapFields($value),
                ],
            ];
        }

        throw new RuntimeException('Unsupported Firestore value type: '.get_debug_type($value));
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isListArray(array $value): bool
    {
        return array_values($value) === $value;
    }

    /**
     * @return list<string>
     */
    private function pathSegments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
    }

    /**
     * @return array<string, int|string|null|list<string>>
     */
    private function payload(CsvExportTask $task): array
    {
        $totalRows = max((int) $task->total_rows, 1);
        $generatedRows = max((int) $task->generated_rows, 0);

        return [
            'task_id' => (int) $task->id,
            'user_id' => (int) $task->user_id,
            'status' => $task->status,
            'file_name' => $task->file_name,
            'columns' => array_values($task->columns ?? []),
            'total_rows' => (int) $task->total_rows,
            'generated_rows' => (int) $task->generated_rows,
            'progress_percentage' => (int) min(100, floor(($generatedRows / $totalRows) * 100)),
            'interval_seconds' => (int) $task->interval_seconds,
            'queue_name' => $task->queue_name,
            'last_error' => $task->last_error,
            'started_at' => $task->started_at?->toISOString(),
            'finished_at' => $task->finished_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }
}
