<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubdomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = config('app.base_domain');

        if (! $baseDomain) {
            $business = Business::withoutGlobalScopes()
                ->where('status', BusinessStatus::Active)
                ->orderBy('id')
                ->first();

            if ($business) {
                app()->instance('current_business_id', $business->id);
            }

            return $next($request);
        }

        $host = $request->getHost();

        if (! str_ends_with($host, '.' . $baseDomain)) {
            abort(404);
        }

        $subdomain = str($host)->before('.' . $baseDomain)->value();

        if (empty($subdomain)) {
            abort(404);
        }

        $business = Business::withoutGlobalScopes()
            ->where('subdomain', $subdomain)
            ->first();

        if (! $business) {
            abort(404);
        }

        if ($business->status === BusinessStatus::Suspended) {
            abort(503, 'This salon is currently unavailable.');
        }

        app()->instance('current_business_id', $business->id);

        return $next($request);
    }
}
