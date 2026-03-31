<?php

namespace Tests\Unit;

use App\Jobs\GenerateCsvExportRowJob;
use App\Models\CsvExportChannel;
use App\Models\CsvExportTask;
use App\Models\CsvExportTemplate;
use App\Models\User;
use App\Services\CsvExport\CsvExportFakeDataService;
use App\Services\CsvExport\CsvExportTaskFirestoreSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateCsvExportRowJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_appends_one_row_and_requeues_when_more_rows_are_needed(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();

        $template = CsvExportTemplate::query()->create([
            'user_id' => $user->id,
            'name' => 'default-template',
            'columns' => ['serial_no', 'name'],
            'interval_seconds' => 5,
            'queue_name' => 'default',
            'is_active' => true,
        ]);

        $task = CsvExportTask::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'exports/csv/test.csv',
            'template_id' => $template->id,
            'total_rows' => 2,
            'generated_rows' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,name\n");

        $csvExportTaskFirestoreSyncService = Mockery::mock(CsvExportTaskFirestoreSyncService::class);
        $csvExportTaskFirestoreSyncService->shouldReceive('syncTask')->atLeast()->once();

        $job = new GenerateCsvExportRowJob($task->id);
        $job->handle(app(CsvExportFakeDataService::class), $csvExportTaskFirestoreSyncService);

        $task->refresh();

        $this->assertSame(1, $task->generated_rows);
        $this->assertSame(CsvExportTask::STATUS_PROCESSING, $task->status);
        Queue::assertPushed(GenerateCsvExportRowJob::class, 1);

        $content = Storage::disk('local')->get($task->file_path);
        $this->assertGreaterThanOrEqual(2, count(array_filter(explode("\n", trim($content)))));
    }

    #[Test]
    public function job_marks_task_completed_after_last_row_is_written(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();

        $template = CsvExportTemplate::query()->create([
            'user_id' => $user->id,
            'name' => 'single-row-template',
            'columns' => ['serial_no', 'email'],
            'interval_seconds' => 5,
            'queue_name' => 'default',
            'is_active' => true,
        ]);

        $task = CsvExportTask::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'exports/csv/final.csv',
            'template_id' => $template->id,
            'total_rows' => 1,
            'generated_rows' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,email\n");

        $csvExportTaskFirestoreSyncService = Mockery::mock(CsvExportTaskFirestoreSyncService::class);
        $csvExportTaskFirestoreSyncService->shouldReceive('syncTask')->atLeast()->once();

        $job = new GenerateCsvExportRowJob($task->id);
        $job->handle(app(CsvExportFakeDataService::class), $csvExportTaskFirestoreSyncService);

        $task->refresh();

        $this->assertSame(1, $task->generated_rows);
        $this->assertSame(CsvExportTask::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->finished_at);
        Queue::assertNotPushed(GenerateCsvExportRowJob::class);
    }

    #[Test]
    public function job_uses_channel_tag_allowed_values_when_generating_rows(): void
    {
        Storage::fake('local');
        Queue::fake();

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
            'allowed_values' => ['queued', 'done'],
            'sort_order' => 0,
        ]);

        $task = CsvExportTask::factory()->create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'file_path' => 'exports/csv/tag-values.csv',
            'total_rows' => 1,
            'generated_rows' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "status,serial_no\n");

        $csvExportTaskFirestoreSyncService = Mockery::mock(CsvExportTaskFirestoreSyncService::class);
        $csvExportTaskFirestoreSyncService->shouldReceive('syncTask')->atLeast()->once();

        $job = new GenerateCsvExportRowJob($task->id);
        $job->handle(app(CsvExportFakeDataService::class), $csvExportTaskFirestoreSyncService);

        $rows = array_values(array_filter(explode("\n", trim(Storage::disk('local')->get($task->file_path)))));

        $this->assertCount(2, $rows);
        [$generatedStatus] = str_getcsv($rows[1]);

        $this->assertContains($generatedStatus, ['queued', 'done']);
    }
}
