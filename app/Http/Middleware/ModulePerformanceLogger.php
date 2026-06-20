<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ModulePerformanceLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $slowWriteDetected = false;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs, &$slowWriteDetected): void {
            $queryCount++;
            $queryTimeMs += $query->time;

            $sql = strtolower(trim($query->sql));
            $isWriteOrLock = str_starts_with($sql, 'update')
                || str_starts_with($sql, 'insert')
                || str_starts_with($sql, 'delete')
                || str_contains($sql, 'for update');

            if ($isWriteOrLock && $query->time >= 200) {
                $slowWriteDetected = true;
            }
        });

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->writeLog(
                request: $request,
                status: 500,
                durationMs: (microtime(true) - $startedAt) * 1000,
                queryCount: $queryCount,
                queryTimeMs: $queryTimeMs,
                slowWriteDetected: $slowWriteDetected,
                exceptionMessage: $exception->getMessage(),
            );

            throw $exception;
        }

        $this->writeLog(
            request: $request,
            status: $response->getStatusCode(),
            durationMs: (microtime(true) - $startedAt) * 1000,
            queryCount: $queryCount,
            queryTimeMs: $queryTimeMs,
            slowWriteDetected: $slowWriteDetected,
        );

        return $response;
    }

    private function writeLog(
        Request $request,
        int $status,
        float $durationMs,
        int $queryCount,
        float $queryTimeMs,
        bool $slowWriteDetected,
        ?string $exceptionMessage = null,
    ): void {
        $module = $this->detectModule($request);

        if ($module === null) {
            return;
        }

        $bottleneck = $this->detectBottleneck(
            status: $status,
            durationMs: $durationMs,
            queryCount: $queryCount,
            queryTimeMs: $queryTimeMs,
            slowWriteDetected: $slowWriteDetected,
            exceptionMessage: $exceptionMessage,
        );

        $route = $request->route()?->uri() ?? $request->path();

        Log::build([
            'driver' => 'single',
            'path' => storage_path("logs/{$module}.log"),
            'locking' => true,
        ])->info('API performance', [
            'endpoint' => $request->method() . ' /' . ltrim($route, '/'),
            'time_ms' => round($durationMs, 2),
            'status' => $status,
            'bottleneck' => $bottleneck,
        ]);
    }

    private function detectBottleneck(
        int $status,
        float $durationMs,
        int $queryCount,
        float $queryTimeMs,
        bool $slowWriteDetected,
        ?string $exceptionMessage,
    ): string {
        $message = strtolower((string) $exceptionMessage);

        if (
            $slowWriteDetected
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
        ) {
            return 'possible_lock_contention';
        }

        if ($status >= 500) {
            return 'application_error';
        }

        if ($queryCount >= 20) {
            return 'possible_n_plus_one';
        }

        if ($queryTimeMs >= 150 && $queryTimeMs >= ($durationMs * 0.60)) {
            return 'database';
        }

        if ($durationMs >= 500) {
            return 'slow_application';
        }

        return 'none';
    }

    private function detectModule(Request $request): ?string
    {
        $action = strtolower((string) $request->route()?->getActionName());
        $path = strtolower($request->path());

        if (str_contains($action, 'modules\\product') || str_contains($path, 'products')) {
            return 'product';
        }

        if (str_contains($action, 'modules\\order') || str_contains($path, 'orders')) {
            return 'order';
        }

        if (str_contains($action, 'modules\\category') || str_contains($path, 'categories')) {
            return 'category';
        }

        return null;
    }
}
