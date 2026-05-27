<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $business = auth()->user()->business;
            if ($business && $business->status === BusinessStatus::Suspended) {
                auth()->logout();
                abort(503, 'This salon is currently unavailable.');
            }
        }

        return $next($request);
    }
}
