<?php

namespace App\Jobs;

use App\Models\CsvExportTask;
use App\Services\Export\CsvExportFakeDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateCsvExportRowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $taskId) {}

    public function handle(CsvExportFakeDataService $fakeDataService): void
    {
        $task = CsvExportTask::query()->find($this->taskId);

        if (! $task instanceof CsvExportTask) {
            return;
        }

        if (in_array($task->status, [CsvExportTask::STATUS_COMPLETED, CsvExportTask::STATUS_FAILED], true)) {
            return;
        }

        try {
            $disk = Storage::disk($task->disk);

            if (! $disk->exists($task->file_path)) {
                throw new RuntimeException('CSV export file does not exist.');
            }

            if ($task->started_at === null) {
                $task->forceFill([
                    'status' => CsvExportTask::STATUS_PROCESSING,
                    'started_at' => now(),
                ])->save();
            }

            $sequence = $task->generated_rows + 1;
            $row = $fakeDataService->generateRow($task->columns ?? [], $sequence);
            $this->appendCsvRow($task, $row);

            $task->forceFill([
                'generated_rows' => $sequence,
                'status' => $sequence >= $task->total_rows
                    ? CsvExportTask::STATUS_COMPLETED
                    : CsvExportTask::STATUS_PROCESSING,
                'finished_at' => $sequence >= $task->total_rows ? now() : null,
                'last_error' => null,
            ])->save();

            if ($sequence < $task->total_rows) {
                static::dispatch($task->id)
                    ->onQueue($task->queue_name)
                    ->delay(now()->addSeconds($task->interval_seconds));
            }
        } catch (\Throwable $throwable) {
            $task->forceFill([
                'status' => CsvExportTask::STATUS_FAILED,
                'last_error' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

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
}
