<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! $request->routeIs('logout')) {
            $business = auth()->user()->business;
            if ($business && ! $business->hasAccess()) {
                return response()->view('errors.subscription-expired', ['business' => $business], 503);
            }
        }

        return $next($request);
    }
}
