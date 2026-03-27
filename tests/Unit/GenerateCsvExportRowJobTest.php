<?php

namespace Tests\Unit;

use App\Jobs\GenerateCsvExportRowJob;
use App\Models\CsvExportTask;
use App\Services\Export\CsvExportFakeDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

        $task = CsvExportTask::factory()->create([
            'file_path' => 'exports/csv/test.csv',
            'columns' => ['serial_no', 'name'],
            'total_rows' => 2,
            'generated_rows' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,name\n");

        $job = new GenerateCsvExportRowJob($task->id);
        $job->handle(app(CsvExportFakeDataService::class));

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

        $task = CsvExportTask::factory()->create([
            'file_path' => 'exports/csv/final.csv',
            'columns' => ['serial_no', 'email'],
            'total_rows' => 1,
            'generated_rows' => 0,
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,email\n");

        $job = new GenerateCsvExportRowJob($task->id);
        $job->handle(app(CsvExportFakeDataService::class));

        $task->refresh();

        $this->assertSame(1, $task->generated_rows);
        $this->assertSame(CsvExportTask::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->finished_at);
        Queue::assertNotPushed(GenerateCsvExportRowJob::class);
    }
}
