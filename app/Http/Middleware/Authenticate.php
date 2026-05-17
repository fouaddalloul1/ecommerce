<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * IMPORTANT: prevent Laravel from redirecting to login route
     */
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return null; 
    }
}
