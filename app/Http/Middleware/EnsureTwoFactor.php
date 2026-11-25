<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Administratorzy muszą mieć skonfigurowane 2FA i przejść weryfikację w tej sesji.
 * Nieaktywne konta są wylogowywane.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }
        if ($user->isAdmin()) {
            if (! $user->hasTwoFactor()) {
                return redirect()->route('two-factor.setup');
            }
            if (! $request->session()->get('two_factor_passed')) {
                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
