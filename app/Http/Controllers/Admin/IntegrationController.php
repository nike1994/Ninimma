<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Services\Social\Integrations;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Administracja → Integracje: włączniki i klucze aplikacji Meta / TikTok. */
class IntegrationController extends Controller
{
    public function edit()
    {
        return view('admin.integrations', [
            'providers' => collect(Integrations::PROVIDERS)->mapWithKeys(fn ($p) => [$p => [
                'enabled' => (bool) Setting::get("int_{$p}_enabled", false),
                'ready' => Integrations::enabled($p),
                'key' => Integrations::key($p),
                'has_secret' => filled(Integrations::secret($p)),
                'redirect' => Integrations::redirectUri($p),
                'accounts' => SocialAccount::whereIn('platform', $p === 'meta' ? ['facebook', 'instagram'] : ['tiktok'])->whereNotNull('connected_at')->count(),
            ]]),
            'metaAds' => [
                'enabled' => (bool) Setting::get('int_meta_ads_enabled', false), 'ready' => Integrations::metaAdsEnabled(),
                'has_token' => filled(Integrations::metaAdsToken()),
                'ads' => \App\Models\AdCampaign::where('platform', 'meta')->whereNotNull('external_id')->count(),
            ],
            'googleAds' => Integrations::googleAds() + ['ready' => Integrations::googleAdsEnabled(),
                'ads' => \App\Models\AdCampaign::where('platform', 'google')->whereNotNull('external_id')->count()],
            'tiktokAds' => ['enabled' => (bool) Setting::get('int_ttads_enabled', false), 'ready' => Integrations::tiktokAdsEnabled(),
                'has_token' => filled(Integrations::encrypted('int_ttads_token')),
                'ads' => \App\Models\AdCampaign::where('platform', 'tiktok')->whereNotNull('external_id')->count()],
            'autoStats' => Integrations::autoStats(),
            'tiktokPrivacy' => Integrations::tiktokPrivacy(),
            'recent' => Publication::with(['content', 'account'])->whereNotNull('api_status')->latest('updated_at')->limit(12)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(Integrations::PROVIDERS)],
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'tiktok_privacy' => ['nullable', Rule::in(['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'PUBLIC_TO_EVERYONE'])],
        ]);
        Integrations::save($data['provider'], $request->boolean('enabled'), $data['key'] ?? null, $data['secret'] ?? null);
        if ($data['provider'] === 'tiktok' && ! empty($data['tiktok_privacy'])) {
            Setting::put('int_tiktok_privacy', $data['tiktok_privacy']);
        }

        return back()->with('status', __('ui.saved'));
    }

    public function metaAds(Request $request)
    {
        $data = $request->validate(['token' => ['nullable', 'string', 'max:1000']]);
        Integrations::saveMetaAds($request->boolean('enabled'), $data['token'] ?? null);

        return back()->with('status', __('ui.saved'));
    }

    public function googleAds(Request $request)
    {
        $data = $request->validate([
            'developer_token' => ['nullable', 'string', 'max:255'], 'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'], 'refresh_token' => ['nullable', 'string', 'max:1000'],
            'login_customer_id' => ['nullable', 'regex:/^[\d-]{10,12}$/'], 'version' => ['nullable', 'regex:/^v\d{2}$/'],
        ]);
        Setting::put('int_gads_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::put('int_gads_client_id', trim((string) ($data['client_id'] ?? '')) ?: null);
        Setting::put('int_gads_login_customer', $data['login_customer_id'] ?? null);
        Setting::put('int_gads_version', $data['version'] ?? null);
        Integrations::putEncrypted('int_gads_dev_token', $data['developer_token'] ?? null);
        Integrations::putEncrypted('int_gads_client_secret', $data['client_secret'] ?? null);
        Integrations::putEncrypted('int_gads_refresh_token', $data['refresh_token'] ?? null);
        \Illuminate\Support\Facades\Cache::forget('gads_access_token');

        return back()->with('status', __('ui.saved'));
    }

    public function tiktokAds(Request $request)
    {
        $data = $request->validate(['token' => ['nullable', 'string', 'max:1000']]);
        Setting::put('int_ttads_enabled', $request->boolean('enabled') ? '1' : '0');
        Integrations::putEncrypted('int_ttads_token', $data['token'] ?? null);

        return back()->with('status', __('ui.saved'));
    }

    public function stats(Request $request)
    {
        Setting::put('int_auto_stats', $request->boolean('auto_stats') ? '1' : '0');

        return back()->with('status', __('ui.saved'));
    }
}
