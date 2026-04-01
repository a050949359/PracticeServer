<?php

namespace App\Services\CsvExport;

use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CsvExportTaskInfluxSyncService
{
    private const ERROR_SAMPLE_LIMIT = 5;

    public function importPendingTasks(int $limit = 50): int
    {
        $report = $this->importPendingTasksReport($limit);

        return (int) $report['imported_rows'];
    }

    /**
     * @return array{sync_enabled: bool, limit: int, tasks_selected: int, tasks_processed: int, tasks_imported: int, tasks_skipped: int, imported_rows: int, skip_reasons: array<string, int>, error_samples: list<array{task_id: int, reason: string, detail: string}>}
     */
    public function importPendingTasksReport(int $limit = 50): array
    {
        $normalizedLimit = max(1, $limit);

        if (! $this->isSyncEnabled()) {
            Log::info('Influx import skipped because sync is disabled.');

            return [
                'sync_enabled' => false,
                'limit' => $normalizedLimit,
                'tasks_selected' => 0,
                'tasks_processed' => 0,
                'tasks_imported' => 0,
                'tasks_skipped' => 0,
                'imported_rows' => 0,
                'skip_reasons' => [
                    'sync_disabled' => 1,
                ],
                'error_samples' => [],
            ];
        }

        $tasks = CsvExportTask::query()
            ->with(['template', 'channel.tags', 'channel.fields'])
            ->whereIn('status', [CsvExportTask::STATUS_PROCESSING, CsvExportTask::STATUS_COMPLETED])
            ->whereColumn('generated_rows', '>', 'last_influx_imported_row')
            ->orderBy('id')
            ->limit($normalizedLimit)
            ->get();

        $importedRows = 0;
        $tasksProcessed = 0;
        $tasksImported = 0;
        $tasksSkipped = 0;
        $skipReasons = [];
        $errorSamples = [];

        foreach ($tasks as $task) {
            if (! $task instanceof CsvExportTask) {
                continue;
            }

            $tasksProcessed++;
            $result = $this->importTaskWithReason($task);
            $importedRows += $result['imported_rows'];

            if ($result['imported_rows'] > 0) {
                $tasksImported++;
            } else {
                $tasksSkipped++;
                $reason = $result['reason'];
                $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;

                if ($result['detail'] !== '' && count($errorSamples) < self::ERROR_SAMPLE_LIMIT) {
                    $errorSamples[] = [
                        'task_id' => $task->id,
                        'reason' => $reason,
                        'detail' => $result['detail'],
                    ];
                }
            }
        }

        return [
            'sync_enabled' => true,
            'limit' => $normalizedLimit,
            'tasks_selected' => $tasks->count(),
            'tasks_processed' => $tasksProcessed,
            'tasks_imported' => $tasksImported,
            'tasks_skipped' => $tasksSkipped,
            'imported_rows' => $importedRows,
            'skip_reasons' => $skipReasons,
            'error_samples' => $errorSamples,
        ];
    }

    public function importTask(CsvExportTask $task): int
    {
        $result = $this->importTaskWithReason($task);

        return $result['imported_rows'];
    }

    /**
     * @return array{imported_rows: int, reason: string, detail: string}
     */
    private function importTaskWithReason(CsvExportTask $task): array
    {
        if (! $this->isSyncEnabled()) {
            return [
                'imported_rows' => 0,
                'reason' => 'sync_disabled',
                'detail' => '',
            ];
        }

        $task->loadMissing(['template', 'channel.tags', 'channel.fields']);
        $rowsToImport = max(0, (int) $task->generated_rows - (int) $task->last_influx_imported_row);

        if ($rowsToImport === 0) {
            return [
                'imported_rows' => 0,
                'reason' => 'no_pending_rows',
                'detail' => '',
            ];
        }

        $disk = Storage::disk($task->disk);
        if (! $disk->exists($task->file_path)) {
            Log::warning('CSV file not found for Influx import.', [
                'task_id' => $task->id,
                'file_path' => $task->file_path,
            ]);

            return [
                'imported_rows' => 0,
                'reason' => 'file_not_found',
                'detail' => 'CSV file does not exist: '.$task->file_path,
            ];
        }

        try {
            $token = $this->influxToken();
            $database = $this->influxDatabase();
            $baseUrl = rtrim((string) config('services.influxdb.url', 'http://influxdb:8086'), '/');
            $channel = $this->resolveChannel($task);

            if (! $channel instanceof CsvExportChannel) {
                return [
                    'imported_rows' => 0,
                    'reason' => 'channel_not_resolved',
                    'detail' => 'Unable to resolve active channel from task channel_id/file_name.',
                ];
            }

            $lineProtocol = $this->taskCsvLineProtocol($task, $channel, $disk->path($task->file_path));

            if ($lineProtocol === []) {
                return [
                    'imported_rows' => 0,
                    'reason' => 'no_valid_line_protocol',
                    'detail' => 'CSV rows did not produce valid line protocol for InfluxDB.',
                ];
            }

            $body = implode("\n", $lineProtocol);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'text/plain; charset=utf-8',
            ])->connectTimeout(3)
                ->timeout(10)
                ->withBody($body, 'text/plain; charset=utf-8')
                ->withOptions([
                    'query' => [
                        'db' => $database,
                        'precision' => 'second',
                        'accept_partial' => 'false',
                    ],
                ])
                ->post($baseUrl.'/api/v3/write_lp');

            if ($response->failed()) {
                Log::warning('InfluxDB write returned failed HTTP status.', [
                    'task_id' => $task->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'imported_rows' => 0,
                    'reason' => 'http_failed_response',
                    'detail' => sprintf(
                        'HTTP %d: %s',
                        $response->status(),
                        $this->truncateDetail($response->body())
                    ),
                ];
            }

            $task->forceFill([
                'last_influx_imported_row' => (int) $task->generated_rows,
            ])->save();

            return [
                'imported_rows' => count($lineProtocol),
                'reason' => 'imported',
                'detail' => '',
            ];
        } catch (ConnectionException $connectionException) {
            Log::error('InfluxDB service did not respond.', [
                'task_id' => $task->id,
                'error' => $connectionException->getMessage(),
            ]);

            return [
                'imported_rows' => 0,
                'reason' => 'service_unreachable',
                'detail' => $this->truncateDetail($connectionException->getMessage()),
            ];
        } catch (Throwable $throwable) {
            Log::warning('Failed to sync CSV export task to InfluxDB.', [
                'task_id' => $task->id,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'imported_rows' => 0,
                'reason' => 'write_failed',
                'detail' => $this->truncateDetail($throwable->getMessage()),
            ];
        }
    }

    private function truncateDetail(string $detail, int $maxLength = 240): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $detail) ?? '');

        if ($normalized === '') {
            return '(empty response body)';
        }

        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $maxLength - 3).'...';
    }

    private function isSyncEnabled(): bool
    {
        return (bool) config('services.influxdb.sync_enabled', false);
    }

    /**
     * @return list<string>
     */
    private function taskCsvLineProtocol(CsvExportTask $task, CsvExportChannel $channel, string $filePath): array
    {
        $measurement = $this->escapeMeasurement((string) $channel->measurement);
        $rows = $this->readCsvRows($filePath);

        if ($rows === []) {
            return [];
        }

        $header = array_values(array_filter(array_map(fn ($value): string => trim((string) $value), $rows[0]), fn ($value): bool => $value !== ''));

        if ($header === []) {
            return [];
        }

        $startDataIndex = max(1, (int) $task->last_influx_imported_row + 1);
        $endDataIndex = min((int) $task->generated_rows, count($rows) - 1);

        if ($endDataIndex < $startDataIndex) {
            return [];
        }

        $lineProtocol = [];
        for ($dataRowNumber = $startDataIndex; $dataRowNumber <= $endDataIndex; $dataRowNumber++) {
            $row = $rows[$dataRowNumber] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $rowValues = [];
            foreach ($header as $index => $columnName) {
                $rowValues[$columnName] = (string) ($row[$index] ?? '');
            }

            $tags = $this->mapTagValues($channel, $rowValues);
            $fields = $this->mapFieldValues($channel, $rowValues, $dataRowNumber);

            if (count($fields) === 1) {
                continue;
            }

            $tagPairs = [];
            foreach ($tags as $key => $value) {
                $tagPairs[] = $this->escapeTagKey($key).'='.$this->escapeTagValue($value);
            }

            $fieldPairs = [];
            foreach ($fields as $key => $value) {
                $fieldPairs[] = $this->escapeFieldKey($key).'='.$value;
            }

            $lineProtocol[] = implode(',', array_merge([$measurement], $tagPairs)).' '.implode(',', $fieldPairs).' '.$this->resolveTimestamp($task);
        }

        return $lineProtocol;
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file for Influx import.');
        }

        $rows = [];
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function toFieldValue(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '""';
        }

        if (filter_var($trimmed, FILTER_VALIDATE_INT) !== false) {
            return $trimmed.'i';
        }

        if (is_numeric($trimmed)) {
            return (string) (float) $trimmed;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $trimmed).'"';
    }

    /**
     * @param  array<string, string>  $rowValues
     * @return array<string, string>
     */
    private function mapTagValues(CsvExportChannel $channel, array $rowValues): array
    {
        $tags = [];

        foreach ($channel->tags->sortBy('sort_order') as $tag) {
            $columnName = (string) $tag->column_name;

            if ($columnName === '' || ! array_key_exists($columnName, $rowValues)) {
                continue;
            }

            $tags[$columnName] = $rowValues[$columnName];
        }

        return $tags;
    }

    /**
     * @param  array<string, string>  $rowValues
     * @return array<string, string>
     */
    private function mapFieldValues(CsvExportChannel $channel, array $rowValues, int $dataRowNumber): array
    {
        $fields = [
            'row_number' => $dataRowNumber.'i',
        ];

        foreach ($channel->fields->sortBy('sort_order') as $field) {
            $columnName = (string) $field->column_name;

            if ($columnName === '' || ! array_key_exists($columnName, $rowValues)) {
                continue;
            }

            $fields[$columnName] = $this->castFieldValueByType($rowValues[$columnName], (string) $field->data_type);
        }

        return $fields;
    }

    private function castFieldValueByType(string $value, string $dataType): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '""';
        }

        return match ($dataType) {
            'int' => ((int) $trimmed).'i',
            'float' => (string) ((float) $trimmed),
            'bool' => in_array(strtolower($trimmed), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false',
            default => $this->toFieldValue($trimmed),
        };
    }

    private function influxToken(): string
    {
        $token = trim((string) config('services.influxdb.token', ''));
        if ($token === '') {
            throw new RuntimeException('INFLUXDB_TOKEN is required.');
        }

        return $token;
    }

    private function influxDatabase(): string
    {
        $database = trim((string) config('services.influxdb.database', ''));
        if ($database === '') {
            throw new RuntimeException('INFLUXDB_DATABASE is required.');
        }

        return $database;
    }

    private function resolveTimestamp(CsvExportTask $task): int
    {
        $source = (string) ($this->resolveChannel($task)?->timestamp_source ?? 'now');

        return match ($source) {
            'task_created_at' => $task->created_at?->timestamp ?? now()->timestamp,
            'task_started_at' => $task->started_at?->timestamp ?? now()->timestamp,
            'task_finished_at' => $task->finished_at?->timestamp ?? now()->timestamp,
            'task_updated_at' => $task->updated_at?->timestamp ?? now()->timestamp,
            default => now()->timestamp,
        };
    }

    private function resolveChannel(CsvExportTask $task): ?CsvExportChannel
    {
        if ($task->channel instanceof CsvExportChannel && $task->channel->is_active) {
            return $task->channel;
        }

        if (! preg_match('/__channel_([A-Za-z0-9_-]+)__/', $task->file_name, $matches)) {
            return null;
        }

        $code = $matches[1] ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        return CsvExportChannel::query()
            ->with(['tags', 'fields'])
            ->where('user_id', $task->user_id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    private function escapeMeasurement(string $value): string
    {
        return str_replace([' ', ',', '='], ['\\ ', '\\,', '\\='], $value);
    }

    private function escapeTagKey(string $value): string
    {
        return str_replace([' ', ',', '='], ['\\ ', '\\,', '\\='], $value);
    }

    private function escapeTagValue(string $value): string
    {
        return str_replace([' ', ',', '='], ['\\ ', '\\,', '\\='], $value);
    }

    private function escapeFieldKey(string $value): string
    {
        return str_replace([' ', ',', '='], ['\\ ', '\\,', '\\='], $value);
    }
}
