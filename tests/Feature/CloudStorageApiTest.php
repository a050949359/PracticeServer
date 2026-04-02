<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CloudStorageApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_cloud_storage_upload_requires_authentication(): void
    {
        $response = $this->post('/api/cloud/storage/upload', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_cloud_storage_upload_forbids_non_staff_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/cloud/storage/upload', [
            'file' => UploadedFile::fake()->create('sample.txt', 10, 'text/plain'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden: staff role required',
                'code' => 'forbidden_staff_only',
            ]);
    }

    #[Test]
    public function test_cloud_storage_upload_stores_file_on_configured_disk(): void
    {
        Storage::fake('s3');
        config()->set('services.cloud_storage.disk', 's3');

        $this->actingAsStaffUser();

        $response = $this->post('/api/cloud/storage/upload', [
            'file' => UploadedFile::fake()->create('sample.txt', 10, 'text/plain'),
            'directory' => 'exports',
            'file_name' => 'sample.txt',
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud storage upload success',
                'code' => 'cloud_storage_upload_success',
                'data' => [
                    'provider' => 'cloud_storage',
                    'disk' => 's3',
                    'path' => 'exports/sample.txt',
                ],
            ]);

        $this->assertTrue(Storage::disk('s3')->exists('exports/sample.txt'));
    }

    #[Test]
    public function test_cloud_storage_list_returns_paginated_items(): void
    {
        Storage::fake('s3');
        config()->set('services.cloud_storage.disk', 's3');

        $this->actingAsStaffUser();

        Storage::disk('s3')->put('exports/a.txt', 'alpha');
        Storage::disk('s3')->put('exports/b.txt', 'beta');

        $response = $this->get('/api/cloud/storage/files?directory=exports&page=1&per_page=1', [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud storage list success',
                'code' => 'cloud_storage_list_success',
            ])
            ->assertJsonPath('data.pagination.page', 1)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2);
    }

    #[Test]
    public function test_cloud_storage_download_returns_file_contents(): void
    {
        Storage::fake('s3');
        config()->set('services.cloud_storage.disk', 's3');

        $this->actingAsStaffUser();

        Storage::disk('s3')->put('exports/report.txt', 'hello-cloud');

        $response = $this->get('/api/cloud/storage/download?path=exports/report.txt', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $this->assertSame('hello-cloud', $response->streamedContent());
    }

    #[Test]
    public function test_cloud_storage_delete_removes_file(): void
    {
        Storage::fake('s3');
        config()->set('services.cloud_storage.disk', 's3');

        $this->actingAsStaffUser();

        Storage::disk('s3')->put('exports/remove-me.txt', 'to-delete');

        $response = $this->delete('/api/cloud/storage/file?path=exports/remove-me.txt', [], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud storage delete success',
                'code' => 'cloud_storage_delete_success',
                'data' => [
                    'path' => 'exports/remove-me.txt',
                    'deleted' => true,
                ],
            ]);

        $this->assertFalse(Storage::disk('s3')->exists('exports/remove-me.txt'));
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
