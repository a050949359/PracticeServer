<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Export\CsvExportStoreRequest;
use App\Jobs\GenerateCsvExportRowJob;
use App\Models\CsvExportTask;
use App\Services\Export\CsvExportFakeDataService;
use App\Services\Export\CsvExportTaskFirestoreSyncService;
use App\Services\Queue\RabbitMqQueueStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvExportController extends Controller
{
    public function __construct(
        private CsvExportFakeDataService $fakeDataService,
        private RabbitMqQueueStatsService $rabbitMqQueueStatsService,
        private CsvExportTaskFirestoreSyncService $csvExportTaskFirestoreSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        $tasks = CsvExportTask::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CsvExportTask $task): array => $this->serializeTask($task))
            ->values()
            ->all();

        return response()->json([
            'message' => 'CSV export tasks loaded',
            'code' => 'csv_export_tasks_loaded',
            'data' => [
                'available_columns' => $this->fakeDataService->availableColumns(),
                'items' => $tasks,
            ],
        ]);
    }

    public function store(CsvExportStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        try {
            $validated = $request->validated();
            $fileName = now()->format('Ymd_His').'.csv';
            $filePath = 'exports/csv/'.$fileName;
            $disk = Storage::disk('local');
            $disk->makeDirectory('exports/csv');
            $this->writeCsvHeader($disk->path($filePath), $validated['columns']);

            $task = CsvExportTask::query()->create([
                'user_id' => $user->id,
                'status' => CsvExportTask::STATUS_PENDING,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'disk' => 'local',
                'columns' => array_values($validated['columns']),
                'total_rows' => (int) $validated['total_rows'],
                'generated_rows' => 0,
                'interval_seconds' => 5,
                'queue_name' => 'default',
            ]);

            GenerateCsvExportRowJob::dispatch($task->id)
                ->onQueue($task->queue_name)
                ->delay(now()->addSeconds($task->interval_seconds));

            $this->csvExportTaskFirestoreSyncService->syncTask($task);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'CSV export task creation failed',
                'code' => 'csv_export_task_create_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'CSV export task created',
            'code' => 'csv_export_task_created',
            'data' => $this->serializeTask($task),
        ], 201);
    }

    public function show(Request $request, CsvExportTask $csvExportTask): JsonResponse
    {
        $user = $request->user();

        if (! $user || $csvExportTask->user_id !== $user->id) {
            return response()->json([
                'message' => 'CSV export task not found',
                'code' => 'csv_export_task_not_found',
            ], 404);
        }

        return response()->json([
            'message' => 'CSV export task loaded',
            'code' => 'csv_export_task_loaded',
            'data' => $this->serializeTask($csvExportTask),
        ]);
    }

    public function queueStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue' => ['nullable', 'string', 'max:64'],
        ]);

        $queueName = (string) ($validated['queue'] ?? config('queue.connections.rabbitmq.queue', 'default'));

        try {
            $stats = $this->rabbitMqQueueStatsService->stats($queueName);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'RabbitMQ queue stats failed',
                'code' => 'rabbitmq_queue_stats_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

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

        return response()->json([
            'message' => 'RabbitMQ queue stats loaded',
            'code' => 'rabbitmq_queue_stats_loaded',
            'data' => array_merge($stats, [
                'active_tasks_count' => $activeTaskCount,
                'active_rows_total' => $activeRowsTotal,
                'active_rows_done' => $activeRowsDone,
                'remaining_rows' => $remainingRows,
                'work_progress_percentage' => $workProgressPercentage,
            ]),
        ]);
    }

    public function download(Request $request, CsvExportTask $csvExportTask): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();

        if (! $user || $csvExportTask->user_id !== $user->id) {
            return response()->json([
                'message' => 'CSV export task not found',
                'code' => 'csv_export_task_not_found',
            ], 404);
        }

        $disk = Storage::disk($csvExportTask->disk);

        if (! $disk->exists($csvExportTask->file_path)) {
            return response()->json([
                'message' => 'CSV export file not found',
                'code' => 'csv_export_file_not_found',
            ], 404);
        }

        return response()->download(
            $disk->path($csvExportTask->file_path),
            $csvExportTask->file_name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
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
     * @return array<string, mixed>
     */
    private function serializeTask(CsvExportTask $task): array
    {
        $totalRows = max((int) $task->total_rows, 1);
        $generatedRows = max((int) $task->generated_rows, 0);
        $progressPercentage = (int) min(100, floor(($generatedRows / $totalRows) * 100));

        return [
            'id' => $task->id,
            'status' => $task->status,
            'file_name' => $task->file_name,
            'columns' => $task->columns,
            'total_rows' => $task->total_rows,
            'generated_rows' => $task->generated_rows,
            'progress_percentage' => $progressPercentage,
            'interval_seconds' => $task->interval_seconds,
            'last_error' => $task->last_error,
            'started_at' => $task->started_at?->toISOString(),
            'finished_at' => $task->finished_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
            'download_url' => '/api/admin/csv-exports/'.$task->id.'/download',
        ];
    }
}
