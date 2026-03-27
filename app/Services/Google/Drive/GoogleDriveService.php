<?php

namespace App\Services\Google\Drive;

use App\Models\GoogleDriveFile;
use App\Models\GoogleOAuthAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveService
{
    public function upload(array $validated, User $user): array
    {
        $googleDriveConfig = $this->googleDriveConfig();
        $folderId = (string) ($googleDriveConfig['folder_id'] ?? '');

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $fileName = (string) ($validated['file_name'] ?? $file->getClientOriginalName());

        $fileBytes = file_get_contents($file->getRealPath());

        if ($fileBytes === false) {
            throw new RuntimeException('Failed to read file bytes.');
        }

        $accessToken = $this->resolveUserAccessToken($user);

        $metadata = [
            'name' => $fileName,
        ];

        if ($folderId !== '') {
            $metadata['parents'] = [$folderId];
        }

        $boundary = 'drive_upload_'.bin2hex(random_bytes(8));
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($metadataJson)) {
            throw new RuntimeException('Failed to encode Drive file metadata.');
        }

        $mimeType = (string) ($file->getMimeType() ?? 'application/octet-stream');

        $multipartBody = "--{$boundary}\r\n";
        $multipartBody .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $multipartBody .= $metadataJson."\r\n";
        $multipartBody .= "--{$boundary}\r\n";
        $multipartBody .= "Content-Type: {$mimeType}\r\n\r\n";
        $multipartBody .= $fileBytes."\r\n";
        $multipartBody .= "--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->withBody($multipartBody, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,size,webViewLink,webContentLink,parents');

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error.message', 'Google Drive upload request failed.');
            throw new RuntimeException($errorMessage);
        }

        $payload = (array) $response->json();
        $driveFileId = (string) ($payload['id'] ?? '');

        if ($driveFileId === '') {
            throw new RuntimeException('Google Drive API did not return a file id.');
        }

        $record = GoogleDriveFile::query()->create([
            'user_id' => $user->id,
            'drive_file_id' => $driveFileId,
            'folder_id' => $folderId !== '' ? $folderId : null,
            'file_name' => (string) ($payload['name'] ?? $fileName),
            'mime_type' => (string) ($payload['mimeType'] ?? $mimeType),
            'file_size' => isset($payload['size']) ? (int) $payload['size'] : $file->getSize(),
            'web_view_link' => isset($payload['webViewLink']) ? (string) $payload['webViewLink'] : null,
            'web_content_link' => isset($payload['webContentLink']) ? (string) $payload['webContentLink'] : null,
            'provider' => 'google_drive',
        ]);

        return [
            'file_id' => $record->drive_file_id,
            'folder_id' => $record->folder_id,
            'file_name' => $record->file_name,
            'mime_type' => $record->mime_type,
            'file_size' => $record->file_size,
            'web_view_link' => $record->web_view_link,
            'web_content_link' => $record->web_content_link,
            'provider' => $record->provider,
            'record' => [
                'id' => $record->id,
                'created_at' => $record->created_at?->toISOString(),
            ],
        ];
    }

    public function list(array $validated, User $user): array
    {
        $query = GoogleDriveFile::query()
            ->where('user_id', $user->id)
            ->latest();
        $keyword = (string) ($validated['keyword'] ?? '');

        if ($keyword !== '') {
            $query->where('file_name', 'like', '%'.$keyword.'%');
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(function (GoogleDriveFile $record): array {
                    return [
                        'id' => $record->id,
                        'file_id' => $record->drive_file_id,
                        'folder_id' => $record->folder_id,
                        'file_name' => $record->file_name,
                        'mime_type' => $record->mime_type,
                        'file_size' => $record->file_size,
                        'web_view_link' => $record->web_view_link,
                        'web_content_link' => $record->web_content_link,
                        'provider' => $record->provider,
                        'created_at' => $record->created_at?->toISOString(),
                    ];
                })
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function download(string $driveFileId, User $user): array
    {
        $record = GoogleDriveFile::query()
            ->where('user_id', $user->id)
            ->where('drive_file_id', $driveFileId)
            ->first();

        if (! $record instanceof GoogleDriveFile) {
            throw new RuntimeException('Google Drive file not found.');
        }

        $accessToken = $this->resolveUserAccessToken($user);

        $response = Http::withToken($accessToken)
            ->accept('*/*')
            ->get('https://www.googleapis.com/drive/v3/files/'.$record->drive_file_id.'?alt=media');

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error.message', 'Google Drive download request failed.');
            throw new RuntimeException($errorMessage);
        }

        return [
            'file_name' => $record->file_name,
            'mime_type' => $record->mime_type ?? 'application/octet-stream',
            'content' => $response->body(),
        ];
    }

    public function delete(string $driveFileId, User $user): array
    {
        $record = GoogleDriveFile::query()
            ->where('user_id', $user->id)
            ->where('drive_file_id', $driveFileId)
            ->first();

        if (! $record instanceof GoogleDriveFile) {
            throw new RuntimeException('Google Drive file not found.');
        }

        $accessToken = $this->resolveUserAccessToken($user);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->delete('https://www.googleapis.com/drive/v3/files/'.$record->drive_file_id);

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error.message', 'Google Drive delete request failed.');
            throw new RuntimeException($errorMessage);
        }

        $fileId = $record->drive_file_id;
        $record->delete();

        return [
            'file_id' => $fileId,
        ];
    }

    private function googleDriveConfig(): array
    {
        return (array) config('services.google_drive', []);
    }

    private function resolveUserAccessToken(User $user): string
    {
        $account = $user->googleOauthAccount;

        if (! $account instanceof GoogleOAuthAccount) {
            throw new RuntimeException('Google Drive account is not connected.');
        }

        if ($account->access_token_expires_at !== null && $account->access_token_expires_at->isFuture()) {
            return (string) $account->access_token;
        }

        if (! is_string($account->refresh_token) || $account->refresh_token === '') {
            throw new RuntimeException('Google Drive refresh token is missing. Please reconnect your account.');
        }

        $oauthClientId = (string) data_get($this->googleDriveConfig(), 'oauth_client_id', '');
        $oauthClientSecret = (string) data_get($this->googleDriveConfig(), 'oauth_client_secret', '');

        if ($oauthClientId === '' || $oauthClientSecret === '') {
            throw new RuntimeException('Google Drive OAuth client is not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => $oauthClientId,
                'client_secret' => $oauthClientSecret,
            ]);

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error_description', data_get($response->json(), 'error', 'Failed to refresh Google Drive access token.'));
            throw new RuntimeException($errorMessage);
        }

        $payload = (array) $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth token endpoint returned empty access token.');
        }

        $expiresIn = max((int) ($payload['expires_in'] ?? 3600), 60);

        $account->forceFill([
            'access_token' => $accessToken,
            'token_type' => (string) ($payload['token_type'] ?? $account->token_type),
            'scope' => (string) ($payload['scope'] ?? $account->scope),
            'access_token_expires_at' => now()->addSeconds($expiresIn - 30),
        ])->save();

        return $accessToken;
    }
}
