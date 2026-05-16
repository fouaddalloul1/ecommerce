<?php

use App\Http\Resources\MainResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


use App\Support\Pulse\System;
use Laravel\Pulse\Facades\Pulse;

return Application::configure(basePath: dirname(__DIR__))


    ->withRouting(
        web: [
            __DIR__ . '/../routes/web.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'capacity' => \App\Http\Middleware\CapacityLimiter::class,
            // 'logJobMetrics' => \App\Jobs\Middleware\LogJobMetrics::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 401 - Unauthenticated
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return MainResource::make(null, 'Unauthenticated.', 401);
            }
        });

        // 404 - Model or route not found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return MainResource::make(null, 'The specified resource cannot be found.', 404);
            }
        });

        // 403 - Forbidden
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return MainResource::make(null, 'This action is unauthorized.', 403);
            }
        });

        // 429 - Capacity limiter (SERVER_BUSY)
        $exceptions->render(function (\Exception $e, Request $request) {
            if ($e->getMessage() === "SERVER_BUSY") {
                return MainResource::make(null, 'Server is busy, please try again shortly.', 429);
            }
        });

        // Fallback for any other exception (500)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {

                // Actual message if exists
                $message = $e->getMessage();

                // If empty → fallback
                if (empty($message)) {
                    $message = 'Something went wrong.';
                }

                return MainResource::make(
                    null,
                    $message,
                    500
                );
            }
        });
    })
    ->create();

Pulse::useSystem(System::class);
