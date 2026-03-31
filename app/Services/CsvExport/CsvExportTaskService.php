<?php

namespace App\Services\CsvExport;

use App\Jobs\GenerateCsvExportRowJob;
use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use App\Models\User;
use App\Services\Queue\RabbitMqQueueStatsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CsvExportTaskService
{
    public function __construct(
        private CsvExportFakeDataService $fakeDataService,
        private RabbitMqQueueStatsService $rabbitMqQueueStatsService,
        private CsvExportTaskFirestoreSyncService $csvExportTaskFirestoreSyncService,
    ) {}

    /**
     * @return array{available_columns: array<string, string>, available_tag_columns: array<string, string>, available_field_columns: array<string, string>, channels: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function indexPayload(User $user): array
    {
        $channels = CsvExportChannel::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['tags', 'fields'])
            ->orderBy('name')
            ->get()
            ->map(function (CsvExportChannel $channel): array {
                return [
                    'id' => $channel->id,
                    'code' => $channel->code,
                    'name' => $channel->name,
                    'measurement' => $channel->measurement,
                    'columns' => $this->channelColumns($channel),
                ];
            })
            ->values()
            ->all();

        $tasks = CsvExportTask::query()
            ->with('channel')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CsvExportTask $task): array => $this->serializeTask($task))
            ->values()
            ->all();

        return [
            'available_columns' => $this->fakeDataService->availableColumns(),
            'available_tag_columns' => $this->fakeDataService->availableColumnsForRole('tag'),
            'available_field_columns' => $this->fakeDataService->availableColumnsForRole('field'),
            'channels' => $channels,
            'items' => $tasks,
        ];
    }

    /**
     * @param  array{channel_id?: int|null, columns?: list<string>, total_rows: int}  $validated
     */
    public function createTask(User $user, array $validated): CsvExportTask
    {
        $channel = null;
        $columns = $validated['columns'] ?? [];

        if (array_key_exists('channel_id', $validated) && $validated['channel_id'] !== null) {
            $channel = CsvExportChannel::query()
                ->with(['tags', 'fields'])
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->find($validated['channel_id']);

            if (! $channel instanceof CsvExportChannel) {
                throw new RuntimeException('Selected CSV channel is not available.');
            }

            $columns = $this->channelColumns($channel);
        }

        if (! is_array($columns) || $columns === []) {
            throw new RuntimeException('CSV export columns are required.');
        }

        $fileName = $this->buildFileName($channel);
        $filePath = 'exports/csv/'.$fileName;
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports/csv');
        $this->writeCsvHeader($disk->path($filePath), $columns);

        $task = CsvExportTask::query()->create([
            'user_id' => $user->id,
            'channel_id' => $channel?->id,
            'status' => CsvExportTask::STATUS_PENDING,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'disk' => 'local',
            'total_rows' => (int) $validated['total_rows'],
            'generated_rows' => 0,
            'last_influx_imported_row' => 0,
        ]);

        GenerateCsvExportRowJob::dispatch($task->id)
            ->onQueue('default')
            ->delay(now()->addSeconds(5));

        $this->csvExportTaskFirestoreSyncService->syncTask($task);

        return $task;
    }

    /**
     * @return array<string, mixed>
     */
    public function queueStats(string $queueName): array
    {
        $stats = $this->rabbitMqQueueStatsService->stats($queueName);

        $activeTasks = CsvExportTask::query()
            ->whereIn('status', [CsvExportTask::STATUS_PENDING, CsvExportTask::STATUS_PROCESSING])
            ->get(['total_rows', 'generated_rows']);

        $activeTaskCount = $activeTasks->count();
        $activeRowsTotal = (int) $activeTasks->sum('total_rows');
        $activeRowsDone = (int) $activeTasks->sum(function (CsvExportTask $task): int {
            return min((int) $task->generated_rows, (int) $task->total_rows);
        });
        $remainingRows = (int) $activeTasks->sum(function (CsvExportTask $task): int {
            return max((int) $task->total_rows - (int) $task->generated_rows, 0);
        });
        $workProgressPercentage = $activeRowsTotal > 0
            ? (int) min(100, floor(($activeRowsDone / $activeRowsTotal) * 100))
            : 100;

        return array_merge($stats, [
            'active_tasks_count' => $activeTaskCount,
            'active_rows_total' => $activeRowsTotal,
            'active_rows_done' => $activeRowsDone,
            'remaining_rows' => $remainingRows,
            'work_progress_percentage' => $workProgressPercentage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTask(CsvExportTask $task): array
    {
        $totalRows = max((int) $task->total_rows, 1);
        $generatedRows = max((int) $task->generated_rows, 0);
        $progressPercentage = (int) min(100, floor(($generatedRows / $totalRows) * 100));

        return [
            'id' => $task->id,
            'status' => $task->status,
            'file_name' => $task->file_name,
            'columns' => $task->template?->columns ?? $this->readTaskColumns($task),
            'channel_id' => $task->channel_id,
            'channel_code' => $task->channel?->code,
            'measurement' => $task->channel?->measurement,
            'total_rows' => $task->total_rows,
            'generated_rows' => $task->generated_rows,
            'progress_percentage' => $progressPercentage,
            'interval_seconds' => $task->template?->interval_seconds ?? 5,
            'last_error' => $task->last_error,
            'started_at' => $task->started_at?->toISOString(),
            'finished_at' => $task->finished_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
            'download_url' => '/api/admin/csv-exports/'.$task->id.'/download',
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    private function writeCsvHeader(string $absolutePath, array $columns): void
    {
        $stream = fopen($absolutePath, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to create CSV export file.');
        }

        try {
            fputcsv($stream, $columns);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return list<string>
     */
    private function channelColumns(CsvExportChannel $channel): array
    {
        $tagColumns = $channel->tags
            ->sortBy('sort_order')
            ->pluck('column_name')
            ->map(fn ($column): string => (string) $column)
            ->all();

        $fieldColumns = $channel->fields
            ->sortBy('sort_order')
            ->pluck('column_name')
            ->map(fn ($column): string => (string) $column)
            ->all();

        return array_values(array_unique(array_merge($tagColumns, $fieldColumns)));
    }

    private function buildFileName(?CsvExportChannel $channel): string
    {
        $timestamp = now()->format('Ymd_His');

        if (! $channel instanceof CsvExportChannel) {
            return $timestamp.'.csv';
        }

        $measurement = Str::of($channel->measurement)->slug('_')->value();

        return sprintf('%s__channel_%s__%s.csv', $timestamp, $channel->code, $measurement);
    }

    /**
     * @return list<string>
     */
    private function readTaskColumns(CsvExportTask $task): array
    {
        $disk = Storage::disk($task->disk);

        if (! $disk->exists($task->file_path)) {
            return [];
        }

        $stream = fopen($disk->path($task->file_path), 'rb');

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
}
