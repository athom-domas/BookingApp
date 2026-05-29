<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('current_business_id')) {
            return $next($request);
        }

        $business = Business::find(app('current_business_id'));

        if ($request->routeIs('filament.admin.pages.abbonamento')) {
            return $next($request);
        }

        if ($business && ! $business->hasAccess()) {
            $user = $request->user();
            if ($user?->isAdmin()) {
                return redirect()->route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]);
            }
            abort(403, 'Il tuo account è sospeso. Contatta l\'amministratore del salone.');
        }

        return $next($request);
    }
}
