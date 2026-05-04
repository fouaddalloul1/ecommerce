<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CapacityLimiter
{
    public function handle($request, Closure $next)
    {
        $key = 'active_order_requests';

        /**
         * Read CPU load from Redis (updated every 2 sec)
         */
        $cpu = (float) Redis::get('system:cpu_load') ?? 0;
        $ram = (float) Redis::get('system:ram_usage') ?? 0;

        /**
         * 1. CPU-based concurrency (non-linear but safe)
         * Instead of 0.4 → use adaptive curve
         */
        $cpuCapacity = intval(
            (100 - $cpu) * (1 - ($cpu / 200))
        );
    

        /**
         * 2. RAM-based capacity
         */
        $ramCapacity = intval(
            (100 - $ram) * 0.6
        );

        /**
         * 3. PHP-FPM limit (VERY IMPORTANT)
         */
        $phpFpmCapacity = 20; // match pm.max_children

        /**
         * 4. DB constraint (VERY IMPORTANT)
         */
        $dbCapacity = 25;

        /**
         * FINAL LIMIT = bottleneck wins
         */
        $limit = max(5, min(
            $cpuCapacity,
            $ramCapacity,
            $phpFpmCapacity,
            $dbCapacity
        ));

        Log::info("Dynamic capacity limit: {$limit}");

        $ttl = 30;

        /**
         * LUA SCRIPT (atomic execution inside Redis)
         */
        $lua = "
            local key = KEYS[1]
            local limit = tonumber(ARGV[1])
            local ttl = tonumber(ARGV[2])

            local current = redis.call('INCR', key)

            if current == 1 then
                redis.call('EXPIRE', key, ttl)
            end

            if current > limit then
                return -1
            end

            return current
        ";

        /**
         * Execute atomic Redis script
         */
        $result = Redis::eval($lua, 1, $key, $limit, $ttl);

        /**
         * Reject overloaded system
         */
        if ($result === -1) {
            Log::warning('Capacity limit reached', [
                'cpu' => $cpu,
                'limit' => $limit,
                'ip' => $request->ip(),
                'route' => $request->path()
            ]);

            abort(429, json_encode([
                'message' => 'Server is busy',
                'cpu' => $cpu,
                'limit' => $limit
            ]));
        }

        try {
            return $next($request);
        } finally {
            Redis::decr($key);
        }
    }
}
