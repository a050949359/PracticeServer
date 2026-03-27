<?php

namespace Tests\Feature;

use App\Jobs\GenerateCsvExportRowJob;
use App\Models\CsvExportTask;
use App\Models\Team;
use App\Models\User;
use App\Services\Queue\RabbitMqQueueStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CsvExportApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function csv_export_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/admin/csv-exports', [
            'columns' => ['serial_no', 'name'],
            'total_rows' => 3,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function csv_export_creation_forbids_non_staff_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/admin/csv-exports', [
            'columns' => ['serial_no', 'name'],
            'total_rows' => 3,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function csv_export_creation_creates_task_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->actingAsStaffUser();

        $response = $this->postJson('/api/admin/csv-exports', [
            'columns' => ['serial_no', 'name', 'email'],
            'total_rows' => 3,
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'CSV export task created',
                'code' => 'csv_export_task_created',
                'data' => [
                    'progress_percentage' => 0,
                ],
            ]);

        $task = CsvExportTask::query()->first();

        $this->assertNotNull($task);
        $this->assertSame($user->id, $task->user_id);
        $this->assertSame(CsvExportTask::STATUS_PENDING, $task->status);
        $this->assertTrue(Storage::disk('local')->exists($task->file_path));
        Queue::assertPushed(GenerateCsvExportRowJob::class, 1);
    }

    #[Test]
    public function csv_export_download_returns_created_file(): void
    {
        Storage::fake('local');

        $user = $this->actingAsStaffUser();

        $task = CsvExportTask::factory()->create([
            'user_id' => $user->id,
            'status' => CsvExportTask::STATUS_COMPLETED,
            'file_name' => '20260327_120000.csv',
            'file_path' => 'exports/csv/20260327_120000.csv',
        ]);

        Storage::disk('local')->put($task->file_path, "serial_no,name\n1,Demo User\n");

        $response = $this->get('/api/admin/csv-exports/'.$task->id.'/download');

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=20260327_120000.csv', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function queue_stats_returns_metrics_for_staff_user(): void
    {
        $this->actingAsStaffUser();

        $this->mock(RabbitMqQueueStatsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('stats')
                ->once()
                ->with('default')
                ->andReturn([
                    'queue' => 'default',
                    'messages_ready' => 3,
                    'messages_unacknowledged' => 1,
                    'messages_total' => 4,
                    'consumers' => 1,
                    'drain_progress_percentage' => 25,
                ]);
        });

        $response = $this->getJson('/api/admin/queue/stats?queue=default');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'RabbitMQ queue stats loaded',
                'code' => 'rabbitmq_queue_stats_loaded',
                'data' => [
                    'queue' => 'default',
                    'messages_ready' => 3,
                    'messages_unacknowledged' => 1,
                    'messages_total' => 4,
                    'consumers' => 1,
                    'drain_progress_percentage' => 25,
                ],
            ]);
    }

    private function actingAsStaffUser(): User
    {
        $user = User::factory()->create();
        $team = Team::query()->firstOrCreate(['name' => 'Staff']);
        $staffRole = Role::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'name' => 'staff',
                'guard_name' => config('auth.defaults.guard'),
            ],
            [
                'is_leader' => false,
            ],
        );

        setPermissionsTeamId($team->id);
        $user->assignRole($staffRole);
        Sanctum::actingAs($user);

        return $user;
    }
}
