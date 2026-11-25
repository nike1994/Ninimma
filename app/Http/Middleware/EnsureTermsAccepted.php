<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

/** Klient musi zaakceptować aktualną wersję regulaminu współpracy. */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->isAdmin() && ! $request->routeIs('terms.*', 'logout')) {
            $current = Setting::termsVersion();
            if ($current > 0 && (int) $user->terms_version < $current) {
                return redirect()->guest(route('terms.show'));
            }
        }

        return $next($request);
    }
}
