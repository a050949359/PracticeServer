<?php

namespace Tests\Unit;

use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use App\Models\User;
use App\Services\CsvExport\CsvExportTaskInfluxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
            'http://influxdb:8086/api/v3/write_lp*' => Http::response('', 204),
        ]);

        config()->set('services.influxdb.sync_enabled', true);
        config()->set('services.influxdb.url', 'http://influxdb:8086');
        config()->set('services.influxdb.token', 'test-token');
        config()->set('services.influxdb.database', 'test-database');

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

        Http::assertSent(function (Request $request): bool {
            $hasAuth = $request->hasHeader('Authorization', 'Bearer test-token');
            $hasWriteEndpoint = str_starts_with($request->url(), 'http://influxdb:8086/api/v3/write_lp?');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $body = $request->body();
            $hasMeasurement = str_contains($body, 'telemetry_measurement,status=queued');
            $hasIntegerField = str_contains($body, 'serial_no=1i');
            $hasStringField = str_contains($body, 'name="Alpha"');

            return $hasAuth
                && $hasWriteEndpoint
                && ($query['db'] ?? null) === 'test-database'
                && ($query['precision'] ?? null) === 'second'
                && ($query['accept_partial'] ?? null) === 'false'
                && $hasMeasurement
                && $hasIntegerField
                && $hasStringField;
        });
    }

    #[Test]
    public function it_reports_http_failure_details_when_influx_returns_an_error(): void
    {
        Storage::fake('local');

        Http::fake([
            'http://influxdb:8086/api/v3/write_lp*' => Http::response('{"code":"unauthorized","message":"token rejected"}', 401),
        ]);

        config()->set('services.influxdb.sync_enabled', true);
        config()->set('services.influxdb.url', 'http://influxdb:8086');
        config()->set('services.influxdb.token', 'bad-token');
        config()->set('services.influxdb.database', 'test-database');

        $user = User::factory()->create();

        $channel = CsvExportChannel::query()->create([
            'user_id' => $user->id,
            'code' => 'telemetry_demo',
            'name' => 'telemetry-demo',
            'measurement' => 'telemetry_measurement',
            'timestamp_source' => 'now',
            'is_active' => true,
        ]);

        $channel->fields()->create([
            'column_name' => 'serial_no',
            'data_type' => 'int',
            'sort_order' => 0,
        ]);

        $task = CsvExportTask::factory()->create([
            'user_id' => $channel->user_id,
            'channel_id' => $channel->id,
            'status' => CsvExportTask::STATUS_COMPLETED,
            'file_name' => '20260331_120000__channel_telemetry_demo__telemetry_measurement.csv',
            'file_path' => 'exports/csv/influx-http-error.csv',
            'total_rows' => 1,
            'generated_rows' => 1,
            'last_influx_imported_row' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no\n1\n");

        $report = app(CsvExportTaskInfluxSyncService::class)->importPendingTasksReport();

        $this->assertSame(0, $report['imported_rows']);
        $this->assertSame(['http_failed_response' => 1], $report['skip_reasons']);
        $this->assertCount(1, $report['error_samples']);
        $this->assertStringContainsString('HTTP 401', $report['error_samples'][0]['detail']);
        $this->assertStringContainsString('token rejected', $report['error_samples'][0]['detail']);
    }
}
