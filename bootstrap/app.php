<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pass values to the stored procedure exactly as sent (keep "" as "", not NULL).
        $middleware->remove(\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only app: always render errors (incl. validation) as JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
    })->create();
