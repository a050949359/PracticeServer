<?php

namespace App\Http\Controllers\Google\Oauth;

use App\Http\Controllers\Controller;
use App\Services\Google\Oauth\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleOAuthService $googleOAuthService) {}

    public function authorizeUrl(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        try {
            $result = $this->googleOAuthService->buildAuthorizeUrl($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Google Drive authorize URL failed',
                'code' => 'google_drive_authorize_url_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Google Drive authorize URL generated',
            'code' => 'google_drive_authorize_url_generated',
            'data' => $result,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect('/admin/google/drive?oauth=failed&reason=invalid_callback');
        }

        try {
            $this->googleOAuthService->callback($code, $state);
        } catch (RuntimeException $exception) {
            return redirect('/admin/google/drive?oauth=failed&reason='.urlencode($exception->getMessage()));
        }

        return redirect('/admin/google/drive?oauth=connected');
    }

    public function status(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        return response()->json([
            'message' => 'Google Drive OAuth status loaded',
            'code' => 'google_drive_oauth_status_loaded',
            'data' => $this->googleOAuthService->status($user),
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        $this->googleOAuthService->disconnect($user);

        return response()->json([
            'message' => 'Google Drive disconnected',
            'code' => 'google_drive_disconnected',
        ]);
    }
}
