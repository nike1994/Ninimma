<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function challenge(Request $request)
    {
        $user = $request->user();
        if (! $user->isAdmin() || $request->session()->get('two_factor_passed')) {
            return redirect()->route('dashboard');
        }
        if (! $user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        $code = trim((string) $request->input('code'));

        $ok = Totp::verify($user->two_factor_secret, $code);
        if (! $ok && $code !== '') {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (($i = array_search(strtoupper($code), $codes, true)) !== false) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                SystemLog::record('auth', 'Recovery code used', ['user' => $user->id], 'warning');
                $ok = true;
            }
        }
        if (! $ok) {
            throw ValidationException::withMessages(['code' => __('auth.2fa_invalid')]);
        }
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 404);
        if ($user->hasTwoFactor()) {
            return redirect()->route('dashboard');
        }
        $secret = $request->session()->get('two_factor_pending') ?? Totp::generateSecret();
        $request->session()->put('two_factor_pending', $secret);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user->email, config('app.name')),
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $secret = $request->session()->get('two_factor_pending');
        abort_unless($user->isAdmin() && $secret && ! $user->hasTwoFactor(), 404);

        if (! Totp::verify($secret, (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('auth.2fa_invalid')]);
        }
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();
        $request->session()->forget('two_factor_pending');
        $request->session()->put('two_factor_passed', true);

        return view('auth.two-factor-codes', ['codes' => $codes]);
    }
}
