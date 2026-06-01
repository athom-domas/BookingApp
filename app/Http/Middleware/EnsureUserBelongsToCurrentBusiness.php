<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToCurrentBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $currentId = Business::currentId();

            if ($user->isAdmin()) {
                if (! $user->businesses()->where('businesses.id', $currentId)->exists()) {
                    abort(403);
                }
            } elseif ($user->business_id !== $currentId) {
                abort(403);
            }
        }

        return $next($request);
    }
}
