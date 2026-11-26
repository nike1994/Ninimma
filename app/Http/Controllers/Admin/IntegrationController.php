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

    public function stats(Request $request)
    {
        Setting::put('int_auto_stats', $request->boolean('auto_stats') ? '1' : '0');

        return back()->with('status', __('ui.saved'));
    }
}
