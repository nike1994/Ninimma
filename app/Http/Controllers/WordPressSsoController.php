<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wspólne logowanie z wtyczki WordPress (ninimma-connect).
 * Wtyczka podpisuje link HMAC-SHA256 sekretem strony; link ważny 5 minut i jednorazowy.
 * Działa tylko dla kont klientów (administratorzy logują się z 2FA).
 */
class WordPressSsoController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'site' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email'],
            'ts' => ['required', 'integer'],
            'nonce' => ['required', 'string', 'max:64'],
            'redirect' => ['nullable', 'string', 'max:2000'],
            'sig' => ['required', 'string', 'size:64'],
        ]);
        $site = Site::where('domain', mb_strtolower(preg_replace('#^https?://|/.*$#', '', $data['site'])))->where('is_active', true)->first();
        $payload = implode('|', [$data['site'], mb_strtolower($data['email']), $data['ts'], $data['nonce'], $data['redirect'] ?? '']);
        $valid = $site && $site->sso_secret
            && abs(time() - (int) $data['ts']) <= 300
            && hash_equals(hash_hmac('sha256', $payload, $site->sso_secret), $data['sig'])
            && Cache::add('sso-nonce:'.$data['nonce'], 1, 600);
        if (! $valid) {
            SystemLog::record('sso_failed', 'WordPress SSO rejected', ['site' => $data['site']], 'warning');
            abort(403, __('ui.sso_invalid'));
        }

        $user = User::where('email', mb_strtolower($data['email']))->where('client_id', $site->client_id)
            ->where('role', 'client')->where('is_active', true)->first();
        if (! $user) {
            return response()->view('auth.sso-no-account', ['email' => $data['email']], 403);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Tylko adresy wewnątrz panelu.
        $redirect = (string) ($data['redirect'] ?? '');

        return redirect(Str::startsWith($redirect, '/') && ! Str::startsWith($redirect, '//') ? $redirect : route('dashboard'));
    }
}
