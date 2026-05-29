<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStorefrontAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('current_business_id')) {
            $business = Business::find(app('current_business_id'));
        } else {
            // Bare-domain path (e.g. APP_BASE_DOMAIN=localhost in dev): SubdomainMiddleware
            // skips the tenant lookup, so we fall back to the first active business ourselves.
            $business = Business::withoutGlobalScopes()
                ->where('status', \App\Enums\BusinessStatus::Active)
                ->orderBy('id')
                ->first();
        }

        if ($business && ! $business->hasAccess()) {
            return response()->view('errors.subscription-expired', ['business' => $business], 503);
        }

        return $next($request);
    }
}
