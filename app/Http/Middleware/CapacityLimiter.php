<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class CapacityLimiter
{
    public function handle($request, Closure $next)
    {
        $key = 'active_order_requests';

        $state = Redis::get('metrics:state') ?? 'LOW';

        $limit = match ($state) {
            'LOW' => 150,
            'MEDIUM' => 100,
            'HIGH' => 50,
            default => 100
        };

        $lua = "
            local key = KEYS[1]
            local limit = tonumber(ARGV[1])

            local current = redis.call('INCR', key)

            if current == 1 then
                redis.call('EXPIRE', key, 1)
            end

            if current > limit then
                return -1
            end

            return current
        ";

        $result = Redis::eval($lua, 1, $key, $limit);

        if ($result === -1) {
            return response()->json([
                'message' => 'Server is busy',
                'state' => $state,
                'limit' => $limit
            ], 429);
        }

        return $next($request);
    }
}
