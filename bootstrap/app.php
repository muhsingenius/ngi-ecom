<?php

use App\Http\Middleware\EnsureDesignerToken;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'designer' => EnsureDesignerToken::class,
        ]);

        $middleware->web(append: [SecurityHeaders::class]);

        // Paystack calls this server-to-server; it is authenticated by its HMAC signature instead of a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/paystack']);

        // Signed-in users who hit guest-only pages (login, register) are sent to their own area.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homePath() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
