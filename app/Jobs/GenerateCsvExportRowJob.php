<?php

namespace App\Jobs;

use App\Models\CsvExportTask;
use App\Services\CsvExport\CsvExportFakeDataService;
use App\Services\CsvExport\CsvExportTaskFirestoreSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateCsvExportRowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $taskId) {}

    public function handle(
        CsvExportFakeDataService $fakeDataService,
        CsvExportTaskFirestoreSyncService $csvExportTaskFirestoreSyncService,
    ): void {
        $task = CsvExportTask::query()->find($this->taskId);

        if (! $task instanceof CsvExportTask) {
            return;
        }

        if (in_array($task->status, [CsvExportTask::STATUS_COMPLETED, CsvExportTask::STATUS_FAILED], true)) {
            return;
        }

        try {
            $task->loadMissing(['template', 'channel.tags']);
            $queueName = (string) ($task->template?->queue_name ?? 'default');
            $intervalSeconds = (int) ($task->template?->interval_seconds ?? 5);

            $disk = Storage::disk($task->disk);

            if (! $disk->exists($task->file_path)) {
                throw new RuntimeException('CSV export file does not exist.');
            }

            $columns = $task->template?->columns;
            if (! is_array($columns) || $columns === []) {
                $columns = $this->readCsvHeader($task);
            }

            if ($columns === []) {
                throw new RuntimeException('CSV export template columns are required.');
            }

            if ($task->started_at === null) {
                $task->forceFill([
                    'status' => CsvExportTask::STATUS_PROCESSING,
                    'started_at' => now(),
                ])->save();

                $csvExportTaskFirestoreSyncService->syncTask($task);
            }

            $sequence = $task->generated_rows + 1;
            $row = $fakeDataService->generateRow($columns, $sequence, $this->tagValueOverrides($task));
            $this->appendCsvRow($task, $row);

            $task->forceFill([
                'generated_rows' => $sequence,
                'status' => $sequence >= $task->total_rows
                    ? CsvExportTask::STATUS_COMPLETED
                    : CsvExportTask::STATUS_PROCESSING,
                'finished_at' => $sequence >= $task->total_rows ? now() : null,
                'last_error' => null,
            ])->save();

            $csvExportTaskFirestoreSyncService->syncTask($task);

            if ($sequence < $task->total_rows) {
                static::dispatch($task->id)
                    ->onQueue($queueName)
                    ->delay(now()->addSeconds($intervalSeconds));
            }
        } catch (\Throwable $throwable) {
            $task->forceFill([
                'status' => CsvExportTask::STATUS_FAILED,
                'last_error' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            $csvExportTaskFirestoreSyncService->syncTask($task);

            throw $throwable;
        }
    }

    /**
     * @param  list<string>  $row
     */
    private function appendCsvRow(CsvExportTask $task, array $row): void
    {
        $stream = fopen(Storage::disk($task->disk)->path($task->file_path), 'ab');

        if ($stream === false) {
            throw new RuntimeException('Unable to open CSV export file for writing.');
        }

        try {
            fputcsv($stream, $row);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return list<string>
     */
    private function readCsvHeader(CsvExportTask $task): array
    {
        $stream = fopen(Storage::disk($task->disk)->path($task->file_path), 'rb');

        if ($stream === false) {
            return [];
        }

        try {
            $header = fgetcsv($stream);

            if (! is_array($header)) {
                return [];
            }

            return array_values(array_filter(array_map(static fn ($column): string => trim((string) $column), $header), static fn (string $column): bool => $column !== ''));
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function tagValueOverrides(CsvExportTask $task): array
    {
        $channel = $task->channel;

        if ($channel === null) {
            return [];
        }

        return $channel->tags
            ->mapWithKeys(function ($tag): array {
                $allowedValues = is_array($tag->allowed_values) ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $tag->allowed_values), static fn (string $value): bool => $value !== '')) : [];

                if ($allowedValues === []) {
                    return [];
                }

                return [
                    (string) $tag->column_name => $allowedValues,
                ];
            })
            ->all();
    }
}
