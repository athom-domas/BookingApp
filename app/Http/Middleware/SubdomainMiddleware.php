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
        // Keep media URLs consistent with the current request's host so that
        // Spatie MediaLibrary doesn't generate URLs pointing to APP_URL (which
        // is static) instead of the actual subdomain being served.
        config(['filesystems.disks.public.url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/storage']);

        $baseDomain = config('app.base_domain');

        if (! $baseDomain) {
            // After SubstituteBindings, the {tenant} route parameter is resolved.
            $tenant = $request->route('tenant');
            if ($tenant instanceof Business) {
                if ($tenant->status === BusinessStatus::Suspended) {
                    abort(503, 'This salon is currently unavailable.');
                }
                app()->instance('current_business_id', $tenant->id);
                return $next($request);
            }

            // Fallback for pre-auth pages (login) and portal routes without a tenant segment.
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
            if ($host !== $baseDomain) {
                abort(404);
            }
            // Bare base domain (e.g. localhost) — no tenant. Show the landing page.
            return $next($request);
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
