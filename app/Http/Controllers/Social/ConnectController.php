<?php

namespace App\Http\Controllers\Social;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\ApiException;
use App\Services\Social\Integrations;
use App\Services\Social\MetaClient;
use App\Services\Social\TikTokClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Łączenie kont z API platform (OAuth) i odłączanie. */
class ConnectController extends Controller
{
    public function redirect(Request $request, SocialAccount $account, MetaClient $meta, TikTokClient $tiktok)
    {
        $provider = Integrations::providerFor($account->platform);
        abort_unless($provider && Integrations::enabled($provider), 404);
        $state = Str::random(40);
        session(['social_connect' => ['state' => $state, 'account' => $account->id, 'provider' => $provider]]);

        return redirect()->away($provider === 'meta' ? $meta->authorizeUrl($state) : $tiktok->authorizeUrl($state));
    }

    public function callback(Request $request, string $provider, MetaClient $meta, TikTokClient $tiktok)
    {
        $s = session()->pull('social_connect');
        abort_unless($s && $s['provider'] === $provider && hash_equals($s['state'], (string) $request->query('state')), 403);
        $account = SocialAccount::findOrFail($s['account']);
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('social.accounts')->with('error', __('api.connect_cancelled'));
        }
        try {
            if ($provider === 'tiktok') {
                $t = $tiktok->exchangeCode($request->query('code'));
                $u = $tiktok->userInfo($t['access_token']);
                $account->forceFill(['external_id' => $t['open_id'], 'access_token' => $t['access_token'], 'refresh_token' => $t['refresh_token'],
                    'token_expires_at' => now()->addSeconds((int) $t['expires_in']), 'connected_at' => now(), 'api_error' => null,
                    'api_meta' => ['name' => $u['display_name'] ?? null, 'username' => $u['username'] ?? null]])->save();

                return redirect()->route('social.accounts')->with('status', __('api.connected', ['name' => $account->name]));
            }
            // Meta: wybór Strony (i konta Instagram), token użytkownika tylko na czas wyboru — zaszyfrowany w sesji.
            $userToken = $meta->exchangeCode($request->query('code'));
            $pages = collect($meta->pages($userToken))->map(fn ($p) => [
                'id' => $p['id'], 'name' => $p['name'], 'token' => encrypt($p['access_token']),
                'ig_id' => $p['instagram_business_account']['id'] ?? null, 'ig_username' => $p['instagram_business_account']['username'] ?? null,
            ])->values()->all();
            session(['social_connect_pages' => ['account' => $account->id, 'pages' => $pages]]);

            return redirect()->route('social.connect.choose');
        } catch (ApiException $e) {
            return redirect()->route('social.accounts')->with('error', __('api.connect_failed', ['error' => $e->getMessage()]));
        }
    }

    public function choose()
    {
        $data = session('social_connect_pages');
        abort_unless($data, 404);

        return view('social.connect-choose', ['account' => SocialAccount::findOrFail($data['account']), 'pages' => $data['pages']]);
    }

    public function attach(Request $request)
    {
        $data = session()->pull('social_connect_pages');
        abort_unless($data, 404);
        $account = SocialAccount::findOrFail($data['account']);
        $page = collect($data['pages'])->firstWhere('id', $request->input('page'));
        abort_unless($page, 422);
        $isIg = $account->platform === Platform::Instagram;
        if ($isIg && ! $page['ig_id']) {
            return redirect()->route('social.accounts')->with('error', __('api.no_instagram', ['page' => $page['name']]));
        }
        $account->forceFill(['external_id' => $isIg ? $page['ig_id'] : $page['id'], 'access_token' => decrypt($page['token']), 'refresh_token' => null,
            'token_expires_at' => null, 'connected_at' => now(), 'api_error' => null,
            'api_meta' => ['name' => $page['name'], 'username' => $isIg ? $page['ig_username'] : null]])->save();

        return redirect()->route('social.accounts')->with('status', __('api.connected', ['name' => $account->name]));
    }

    public function disconnect(SocialAccount $account)
    {
        $account->disconnect();

        return back()->with('status', __('api.disconnected'));
    }
}
