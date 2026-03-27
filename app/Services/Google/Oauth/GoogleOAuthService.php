<?php

namespace App\Services\Google\Oauth;

use App\Models\GoogleOAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleOAuthService
{
    public function buildAuthorizeUrl(User $user): array
    {
        $oauthClientId = (string) config('services.google_drive.oauth_client_id');
        $redirectUri = (string) config('services.google_drive.oauth_redirect_uri');
        $scope = (string) config('services.google_drive.scope', 'https://www.googleapis.com/auth/drive.file');

        if ($oauthClientId === '' || $redirectUri === '') {
            throw new RuntimeException('Google Drive OAuth client_id or redirect_uri is not configured.');
        }

        $state = Str::random(40);
        cache()->put($this->stateCacheKey($state), $user->id, now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => $oauthClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
            'state' => $state,
        ];
    }

    public function callback(string $code, string $state): array
    {
        $userId = cache()->pull($this->stateCacheKey($state));

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            throw new RuntimeException('Invalid Google OAuth state.');
        }

        $user = User::query()->find((int) $userId);

        if (! $user instanceof User) {
            throw new RuntimeException('OAuth user was not found.');
        }

        $oauthClientId = (string) config('services.google_drive.oauth_client_id');
        $oauthClientSecret = (string) config('services.google_drive.oauth_client_secret');
        $redirectUri = (string) config('services.google_drive.oauth_redirect_uri');

        if ($oauthClientId === '' || $oauthClientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('Google Drive OAuth credentials are not configured.');
        }

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $oauthClientId,
                'client_secret' => $oauthClientSecret,
                'redirect_uri' => $redirectUri,
            ]);

        if ($tokenResponse->failed()) {
            $errorMessage = (string) data_get($tokenResponse->json(), 'error_description', data_get($tokenResponse->json(), 'error', 'Failed to exchange Google OAuth code.'));
            throw new RuntimeException($errorMessage);
        }

        $tokenPayload = (array) $tokenResponse->json();
        $accessToken = (string) ($tokenPayload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth token endpoint returned empty access token.');
        }

        $profileResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if ($profileResponse->failed()) {
            $profileError = (string) data_get(
                $profileResponse->json(),
                'error.message',
                data_get($profileResponse->json(), 'error_description', data_get($profileResponse->json(), 'error', 'Unknown error')),
            );

            throw new RuntimeException('Failed to fetch Google user profile: '.$profileError);
        }

        $profile = (array) $profileResponse->json();
        $expiresIn = max((int) ($tokenPayload['expires_in'] ?? 3600), 60);

        GoogleOAuthAccount::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider_user_id' => (string) ($profile['id'] ?? ''),
                'email' => isset($profile['email']) ? (string) $profile['email'] : null,
                'access_token' => $accessToken,
                'refresh_token' => isset($tokenPayload['refresh_token'])
                    ? (string) $tokenPayload['refresh_token']
                    : $user->googleOauthAccount?->refresh_token,
                'token_type' => isset($tokenPayload['token_type']) ? (string) $tokenPayload['token_type'] : null,
                'scope' => isset($tokenPayload['scope']) ? (string) $tokenPayload['scope'] : null,
                'access_token_expires_at' => now()->addSeconds($expiresIn - 30),
            ],
        );

        return [
            'email' => (string) ($profile['email'] ?? ''),
        ];
    }

    public function status(User $user): array
    {
        $account = $user->googleOauthAccount;

        return [
            'connected' => $account instanceof GoogleOAuthAccount,
            'email' => $account?->email,
            'expires_at' => $account?->access_token_expires_at?->toISOString(),
        ];
    }

    public function disconnect(User $user): void
    {
        $user->googleOauthAccount()?->delete();
    }

    private function stateCacheKey(string $state): string
    {
        return 'google_oauth_state_'.$state;
    }
}
