<?php

namespace App\Http\Middleware;

use Closure;
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

        /**
         * Dynamic limit based on system load
         */
        $limit = match (true) {
            $cpu < 30 => 30,
            $cpu < 60 => 20,
            $cpu < 80 => 10,
            default   => 5,
        };

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