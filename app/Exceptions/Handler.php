<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle permission denied exceptions
        $this->renderable(function (PermissionDeniedException $e) {
            return $e->render();
        });

        // Handle authorization exceptions
        $this->renderable(function (AuthorizationException $e) {
            Log::warning('Authorization exception', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id() ?? 'unauthenticated',
                'url' => request()->url(),
                'method' => request()->method(),
            ]);

            return response()->json([
                'message' => 'You are not authorized to perform this action.',
                'error' => 'authorization_denied'
            ], 403);
        });

        // Handle authentication exceptions
        $this->renderable(function (AuthenticationException $e) {
            Log::warning('Authentication exception', [
                'message' => $e->getMessage(),
                'url' => request()->url(),
                'method' => request()->method(),
            ]);

            return response()->json([
                'message' => 'Unauthenticated. Please log in to continue.',
                'error' => 'unauthenticated'
            ], 401);
        });
    }
}
