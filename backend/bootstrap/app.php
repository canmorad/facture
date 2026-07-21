<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Note: statefulApi() already handles session middleware for stateful API routes
        // We only need to add CSRF validation middleware for POST/PUT/DELETE requests
        $middleware->api(prepend: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'check.company' => \App\Http\Middleware\CheckCompanyHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
