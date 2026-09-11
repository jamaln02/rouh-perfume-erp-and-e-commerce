<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\RequireAdminApi;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\AuditAdminRequest;
use App\Http\Middleware\RequirePermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateApiToken::class,
            'admin.token' => RequireAdminApi::class,
            'role' => RequireRole::class,
            'audit.admin' => AuditAdminRequest::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && app()->environment('production') && method_exists($e, 'getStatusCode') && $e->getStatusCode() >= 500) {
                $reference = (string) \Illuminate\Support\Str::uuid();
                report($e);
                return response()->json([
                    'ok' => false,
                    'message' => 'An unexpected server error occurred.',
                    'error_reference' => $reference,
                ], 500);
            }
            return null;
        });
    })->create();
