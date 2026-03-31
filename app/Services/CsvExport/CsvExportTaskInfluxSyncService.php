<?php

namespace App\Services\CsvExport;

use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CsvExportTaskInfluxSyncService
{
    public function importPendingTasks(int $limit = 50): int
    {
        if (! $this->isSyncEnabled()) {
            return 0;
        }

        $tasks = CsvExportTask::query()
            ->with(['template', 'channel.tags', 'channel.fields'])
            ->whereIn('status', [CsvExportTask::STATUS_PROCESSING, CsvExportTask::STATUS_COMPLETED])
            ->whereColumn('generated_rows', '>', 'last_influx_imported_row')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $importedRows = 0;
        foreach ($tasks as $task) {
            if (! $task instanceof CsvExportTask) {
                continue;
            }

            $importedRows += $this->importTask($task);
        }

        return $importedRows;
    }

    public function importTask(CsvExportTask $task): int
    {
        if (! $this->isSyncEnabled()) {
            return 0;
        }

        $task->loadMissing(['template', 'channel.tags', 'channel.fields']);
        $rowsToImport = max(0, (int) $task->generated_rows - (int) $task->last_influx_imported_row);

        if ($rowsToImport === 0) {
            return 0;
        }

        $disk = Storage::disk($task->disk);
        if (! $disk->exists($task->file_path)) {
            Log::warning('CSV file not found for Influx import.', [
                'task_id' => $task->id,
                'file_path' => $task->file_path,
            ]);

            return 0;
        }

        try {
            $token = $this->influxToken();
            $org = $this->influxOrg();
            $bucket = $this->influxBucket();
            $baseUrl = rtrim((string) config('services.influxdb.url', 'http://influxdb:8086'), '/');
            $channel = $this->resolveChannel($task);

            if (! $channel instanceof CsvExportChannel) {
                return 0;
            }

            $lineProtocol = $this->taskCsvLineProtocol($task, $channel, $disk->path($task->file_path));

            if ($lineProtocol === []) {
                return 0;
            }

            $body = implode("\n", $lineProtocol);

            $response = Http::withHeaders([
                'Authorization' => 'Token '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'text/plain; charset=utf-8',
            ])->withBody($body, 'text/plain; charset=utf-8')
                ->post($baseUrl.'/api/v2/write', [
                    'org' => $org,
                    'bucket' => $bucket,
                    'precision' => 's',
                ]);

            if ($response->failed()) {
                throw new RuntimeException('InfluxDB write failed: '.$response->status().' '.$response->body());
            }

            $task->forceFill([
                'last_influx_imported_row' => (int) $task->generated_rows,
            ])->save();

            return count($lineProtocol);
        } catch (Throwable $throwable) {
            Log::warning('Failed to sync CSV export task to InfluxDB.', [
                'task_id' => $task->id,
                'error' => $throwable->getMessage(),
            ]);

            return 0;
        }
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

    private function influxOrg(): string
    {
        $org = trim((string) config('services.influxdb.org', ''));
        if ($org === '') {
            throw new RuntimeException('INFLUXDB_ORG is required.');
        }

        return $org;
    }

    private function influxBucket(): string
    {
        $bucket = trim((string) config('services.influxdb.bucket', 'csv_export_metrics'));
        if ($bucket === '') {
            throw new RuntimeException('INFLUXDB_BUCKET is required.');
        }

        return $bucket;
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
