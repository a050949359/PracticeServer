<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'HTTP Error');

            return response()->json([
                'message' => $message,
            ], $status);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Forbidden',
            ], 403);
        }

        if ($e instanceof TokenMismatchException) {
            return response()->json([
                'message' => 'CSRF token mismatch',
            ], 419);
        }

        if ($e instanceof QueryException) {
            Log::warning($e->getMessage(), [
                'sql_state' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'Database error',
            ], 500);
        }

        Log::error($e);

        return response()->json([
            'message' => 'Internal Server Error',
        ], 500);
    }
}
