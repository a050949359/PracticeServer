<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->teams()->whereIn('name', ['Developer', 'Maintainer', 'Staff'])->exists()) {
            return new JsonResponse([
                'message' => 'Forbidden: staff role required',
                'code' => 'forbidden_staff_only',
            ], 403);
        }

        return $next($request);
    }
}
