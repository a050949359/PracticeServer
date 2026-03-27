<?php

namespace Tests\Feature;

use App\Models\GoogleDriveFile;
use App\Models\GoogleOAuthAccount;
use App\Models\Team;
use App\Models\User;
use App\Services\Google\Drive\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleDriveUploadApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_google_drive_upload_requires_authentication(): void
    {
        $response = $this->post('/api/google/drive/upload', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_google_drive_upload_forbids_non_staff_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/google/drive/upload', [
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
    public function test_google_drive_upload_requires_file_for_staff_user(): void
    {
        $this->actingAsStaffUser();

        $response = $this->post('/api/google/drive/upload', [], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function test_google_drive_upload_returns_uploaded_file_metadata(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn([
                    'file_id' => 'drive_file_123',
                    'folder_id' => 'folder_123',
                    'file_name' => 'sample.jpg',
                    'mime_type' => 'image/jpeg',
                    'file_size' => 1024,
                    'web_view_link' => 'https://drive.google.com/file/d/drive_file_123/view',
                    'web_content_link' => null,
                    'provider' => 'google_drive',
                    'record' => [
                        'id' => 10,
                        'created_at' => '2026-03-27T00:00:00+00:00',
                    ],
                ]);
        });

        $response = $this->post('/api/google/drive/upload', [
            'file' => UploadedFile::fake()->image('sample.jpg'),
            'file_name' => 'sample.jpg',
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Google Drive upload success',
                'code' => 'google_drive_upload_success',
                'data' => [
                    'file_id' => 'drive_file_123',
                    'file_name' => 'sample.jpg',
                    'provider' => 'google_drive',
                ],
            ]);
    }

    #[Test]
    public function test_google_drive_upload_returns_error_payload_when_service_fails(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andThrow(new RuntimeException('credentials missing'));
        });

        $response = $this->post('/api/google/drive/upload', [
            'file' => UploadedFile::fake()->create('sample.txt', 10, 'text/plain'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Google Drive upload failed',
                'code' => 'google_drive_upload_failed',
                'error' => 'credentials missing',
            ]);
    }

    #[Test]
    public function test_google_drive_list_returns_paginated_items(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        GoogleDriveFile::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->get('/api/google/drive/files?per_page=2&page=1', [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Google Drive list success',
                'code' => 'google_drive_list_success',
            ])
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_google_drive_download_returns_file_response(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('download')
                ->once()
                ->andReturn([
                    'file_name' => 'sample.txt',
                    'mime_type' => 'text/plain',
                    'content' => 'demo-content',
                ]);
        });

        $response = $this->get('/api/google/drive/files/drive_file_123/download', [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename="sample.txt"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('demo-content', $response->getContent());
    }

    #[Test]
    public function test_google_drive_delete_returns_success_payload(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('delete')
                ->once()
                ->andReturn([
                    'file_id' => 'drive_file_123',
                ]);
        });

        $response = $this->delete('/api/google/drive/files/drive_file_123', [], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Google Drive delete success',
                'code' => 'google_drive_delete_success',
                'data' => [
                    'file_id' => 'drive_file_123',
                ],
            ]);
    }

    #[Test]
    public function test_google_drive_oauth_status_returns_connection_status(): void
    {
        $user = $this->actingAsStaffUser();

        GoogleOAuthAccount::factory()->create([
            'user_id' => $user->id,
            'email' => 'staff-drive@example.com',
        ]);

        $response = $this->get('/api/google/oauth/status', [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Google Drive OAuth status loaded',
                'code' => 'google_drive_oauth_status_loaded',
                'data' => [
                    'connected' => true,
                    'email' => 'staff-drive@example.com',
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
