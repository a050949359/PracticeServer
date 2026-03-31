<?php

namespace Tests\Unit;

use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use App\Models\User;
use App\Services\CsvExport\CsvExportTaskInfluxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsvExportTaskInfluxSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_skips_import_when_influx_sync_is_disabled(): void
    {
        Http::fake();
        Storage::fake('local');

        config()->set('services.influxdb.sync_enabled', false);

        $task = CsvExportTask::factory()->create([
            'file_path' => 'exports/csv/influx-disabled.csv',
            'generated_rows' => 1,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,name\n1,Disabled\n");

        $importedRows = app(CsvExportTaskInfluxSyncService::class)->importPendingTasks();

        $this->assertSame(0, $importedRows);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_imports_csv_rows_to_influxdb_when_enabled(): void
    {
        Storage::fake('local');

        Http::fake([
            'http://influxdb:8086/api/v2/write*' => Http::response('', 204),
        ]);

        config()->set('services.influxdb.sync_enabled', true);
        config()->set('services.influxdb.url', 'http://influxdb:8086');
        config()->set('services.influxdb.token', 'test-token');
        config()->set('services.influxdb.org', 'test-org');
        config()->set('services.influxdb.bucket', 'test-bucket');
        config()->set('services.influxdb.measurement', 'csv_export_task_progress');

        $user = User::factory()->create();

        $channel = CsvExportChannel::query()->create([
            'user_id' => $user->id,
            'code' => 'telemetry_demo',
            'name' => 'telemetry-demo',
            'measurement' => 'telemetry_measurement',
            'timestamp_source' => 'now',
            'is_active' => true,
        ]);

        $channel->tags()->create([
            'column_name' => 'status',
            'sort_order' => 0,
        ]);

        $channel->fields()->create([
            'column_name' => 'serial_no',
            'data_type' => 'int',
            'sort_order' => 0,
        ]);

        $channel->fields()->create([
            'column_name' => 'name',
            'data_type' => 'string',
            'sort_order' => 1,
        ]);

        $task = CsvExportTask::factory()->create([
            'user_id' => $channel->user_id,
            'channel_id' => $channel->id,
            'status' => CsvExportTask::STATUS_COMPLETED,
            'file_name' => '20260331_120000__channel_telemetry_demo__telemetry_measurement.csv',
            'file_path' => 'exports/csv/influx-import.csv',
            'total_rows' => 2,
            'generated_rows' => 2,
            'last_influx_imported_row' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "status,serial_no,name\nqueued,1,Alpha\ndone,2,Beta\n");

        $importedRows = app(CsvExportTaskInfluxSyncService::class)->importPendingTasks();

        $this->assertSame(2, $importedRows);

        $task->refresh();
        $this->assertSame(2, $task->last_influx_imported_row);

        Http::assertSent(function ($request): bool {
            $hasAuth = $request->hasHeader('Authorization', 'Token test-token');
            $isWriteEndpoint = str_contains($request->url(), '/api/v2/write');
            $body = $request->body();
            $hasMeasurement = str_contains($body, 'telemetry_measurement,status=queued');
            $hasIntegerField = str_contains($body, 'serial_no=1i');
            $hasStringField = str_contains($body, 'name="Alpha"');

            return $hasAuth && $isWriteEndpoint && $hasMeasurement && $hasIntegerField && $hasStringField;
        });
    }
}
