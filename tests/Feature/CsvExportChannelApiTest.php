<?php

namespace Tests\Feature;

use App\Models\CsvExportChannel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CsvExportChannelApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function channel_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/csv-channels');

        $response->assertStatus(401);
    }

    #[Test]
    public function channel_store_creates_channel_with_tags_and_fields(): void
    {
        $this->actingAsStaffUser();

        $response = $this->postJson('/api/admin/csv-channels', [
            'code' => 'task_progress_default',
            'name' => 'task-progress-default',
            'measurement' => 'csv_export_task_progress',
            'timestamp_source' => 'now',
            'is_active' => true,
            'tags' => [
                [
                    'column_name' => 'status',
                    'allowed_values' => ['queued', 'done'],
                ],
            ],
            'fields' => [
                [
                    'column_name' => 'serial_no',
                    'data_type' => 'int',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'code' => 'csv_export_channel_created',
                'data' => [
                    'code' => 'task_progress_default',
                    'name' => 'task-progress-default',
                    'measurement' => 'csv_export_task_progress',
                    'timestamp_source' => 'now',
                ],
            ]);

        $channel = CsvExportChannel::query()->first();
        $this->assertNotNull($channel);
        $this->assertSame('task_progress_default', $channel->code);
        $this->assertSame('task-progress-default', $channel->name);
        $this->assertCount(1, $channel->tags);
        $this->assertSame(['queued', 'done'], $channel->tags->first()->allowed_values);
        $this->assertCount(1, $channel->fields);
    }

    #[Test]
    public function channel_index_returns_available_csv_columns(): void
    {
        $this->actingAsStaffUser();

        $response = $this->getJson('/api/admin/csv-channels');

        $response
            ->assertOk()
            ->assertJson([
                'code' => 'csv_export_channels_loaded',
                'data' => [
                    'available_columns' => [
                        'serial_no' => 'Serial No',
                        'status' => 'Status',
                    ],
                    'available_tag_columns' => [
                        'status' => 'Status',
                    ],
                    'available_field_columns' => [
                        'serial_no' => 'Serial No',
                        'temperature_c' => 'Temperature (C)',
                    ],
                ],
            ]);
    }

    #[Test]
    public function channel_show_returns_channel_for_owner(): void
    {
        $owner = $this->actingAsStaffUser();

        $channel = CsvExportChannel::query()->create([
            'user_id' => $owner->id,
            'code' => 'owner_channel',
            'name' => 'owner-channel',
            'measurement' => 'csv_export_task_progress',
            'timestamp_source' => 'now',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/admin/csv-channels/'.$channel->id);

        $response
            ->assertOk()
            ->assertJson([
                'code' => 'csv_export_channel_loaded',
                'data' => [
                    'id' => $channel->id,
                    'code' => 'owner_channel',
                    'name' => 'owner-channel',
                ],
            ]);
    }

    #[Test]
    public function channel_update_replaces_tags_and_fields(): void
    {
        $user = $this->actingAsStaffUser();

        $channel = CsvExportChannel::query()->create([
            'user_id' => $user->id,
            'code' => 'before_update',
            'name' => 'before-update',
            'measurement' => 'csv_export_task_progress',
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

        $response = $this->patchJson('/api/admin/csv-channels/'.$channel->id, [
            'code' => 'after_update',
            'name' => 'after-update',
            'measurement' => 'csv_export_task_progress_v2',
            'timestamp_source' => 'task_updated_at',
            'tags' => [
                [
                    'column_name' => 'email',
                    'allowed_values' => ['alpha@example.test', 'beta@example.test'],
                    'sort_order' => 0,
                ],
            ],
            'fields' => [
                [
                    'column_name' => 'created_at',
                    'data_type' => 'string',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'code' => 'csv_export_channel_updated',
                'data' => [
                    'code' => 'after_update',
                    'name' => 'after-update',
                    'measurement' => 'csv_export_task_progress_v2',
                    'timestamp_source' => 'task_updated_at',
                ],
            ]);

        $channel->refresh();

        $this->assertSame('after-update', $channel->name);
        $this->assertSame('after_update', $channel->code);
        $this->assertSame('csv_export_task_progress_v2', $channel->measurement);
        $this->assertSame('task_updated_at', $channel->timestamp_source);
        $this->assertCount(1, $channel->tags);
        $this->assertSame('email', $channel->tags->first()->column_name);
        $this->assertSame(['alpha@example.test', 'beta@example.test'], $channel->tags->first()->allowed_values);
        $this->assertCount(1, $channel->fields);
        $this->assertSame('created_at', $channel->fields->first()->column_name);
    }

    #[Test]
    public function channel_destroy_deletes_the_channel(): void
    {
        $user = $this->actingAsStaffUser();

        $channel = CsvExportChannel::query()->create([
            'user_id' => $user->id,
            'code' => 'to_delete',
            'name' => 'to-delete',
            'measurement' => 'csv_export_task_progress',
            'timestamp_source' => 'now',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/admin/csv-channels/'.$channel->id);

        $response
            ->assertOk()
            ->assertJson([
                'code' => 'csv_export_channel_deleted',
            ]);

        $this->assertDatabaseMissing('csv_export_channels', [
            'id' => $channel->id,
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
