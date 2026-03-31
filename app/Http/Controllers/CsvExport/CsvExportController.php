<?php

namespace App\Http\Controllers\CsvExport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Export\CsvExportStoreRequest;
use App\Models\CsvExportTask;
use App\Services\CsvExport\CsvExportTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvExportController extends Controller
{
    public function __construct(private CsvExportTaskService $csvExportTaskService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        return response()->json([
            'message' => 'CSV export tasks loaded',
            'code' => 'csv_export_tasks_loaded',
            'data' => $this->csvExportTaskService->indexPayload($user),
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
            $task = $this->csvExportTaskService->createTask($user, $request->validated());
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
            'data' => $this->csvExportTaskService->serializeTask($task),
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
            'data' => $this->csvExportTaskService->serializeTask($csvExportTask),
        ]);
    }

    public function queueStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue' => ['nullable', 'string', 'max:64'],
        ]);

        $queueName = (string) ($validated['queue'] ?? config('queue.connections.rabbitmq.queue', 'default'));

        try {
            $stats = $this->csvExportTaskService->queueStats($queueName);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'RabbitMQ queue stats failed',
                'code' => 'rabbitmq_queue_stats_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'RabbitMQ queue stats loaded',
            'code' => 'rabbitmq_queue_stats_loaded',
            'data' => $stats,
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
}
