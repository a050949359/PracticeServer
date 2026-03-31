<?php

namespace App\Http\Controllers\CsvExport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Export\CsvExportChannelStoreRequest;
use App\Http\Requests\Admin\Export\CsvExportChannelUpdateRequest;
use App\Models\CsvExportChannel;
use App\Services\CsvExport\CsvExportChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsvExportChannelController extends Controller
{
    public function __construct(private CsvExportChannelService $csvExportChannelService) {}

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
            'message' => 'CSV export channels loaded',
            'code' => 'csv_export_channels_loaded',
            'data' => $this->csvExportChannelService->listForUser($user),
        ]);
    }

    public function store(CsvExportChannelStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        $channel = $this->csvExportChannelService->createForUser($user, $request->validated());

        return response()->json([
            'message' => 'CSV export channel created',
            'code' => 'csv_export_channel_created',
            'data' => $this->csvExportChannelService->serializeChannel($channel),
        ], 201);
    }

    public function show(Request $request, CsvExportChannel $csvExportChannel): JsonResponse
    {
        if (! $this->canAccessChannel($request, $csvExportChannel)) {
            return response()->json([
                'message' => 'CSV export channel not found',
                'code' => 'csv_export_channel_not_found',
            ], 404);
        }

        $csvExportChannel->load(['tags', 'fields']);

        return response()->json([
            'message' => 'CSV export channel loaded',
            'code' => 'csv_export_channel_loaded',
            'data' => $this->csvExportChannelService->serializeChannel($csvExportChannel),
        ]);
    }

    public function update(CsvExportChannelUpdateRequest $request, CsvExportChannel $csvExportChannel): JsonResponse
    {
        if (! $this->canAccessChannel($request, $csvExportChannel)) {
            return response()->json([
                'message' => 'CSV export channel not found',
                'code' => 'csv_export_channel_not_found',
            ], 404);
        }

        $channel = $this->csvExportChannelService->update($csvExportChannel, $request->validated());

        return response()->json([
            'message' => 'CSV export channel updated',
            'code' => 'csv_export_channel_updated',
            'data' => $this->csvExportChannelService->serializeChannel($channel),
        ]);
    }

    public function destroy(Request $request, CsvExportChannel $csvExportChannel): JsonResponse
    {
        if (! $this->canAccessChannel($request, $csvExportChannel)) {
            return response()->json([
                'message' => 'CSV export channel not found',
                'code' => 'csv_export_channel_not_found',
            ], 404);
        }

        $this->csvExportChannelService->delete($csvExportChannel);

        return response()->json([
            'message' => 'CSV export channel deleted',
            'code' => 'csv_export_channel_deleted',
        ]);
    }

    private function canAccessChannel(Request $request, CsvExportChannel $channel): bool
    {
        $user = $request->user();

        return $user !== null && $channel->user_id === $user->id;
    }
}
