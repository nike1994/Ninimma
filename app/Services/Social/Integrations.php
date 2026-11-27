<?php

namespace App\Services\Social;

use App\Enums\Platform;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Włączniki i klucze integracji z platformami (Administracja → Integracje).
 * Wyłączona integracja = wszystko działa ręcznie, jak dotąd.
 */
class Integrations
{
    public const PROVIDERS = ['meta', 'tiktok'];

    public static function providerFor(Platform $platform): ?string
    {
        return match ($platform) {
            Platform::Facebook, Platform::Instagram => 'meta',
            Platform::TikTok => 'tiktok',
            default => null,
        };
    }

    public static function enabled(string $provider): bool
    {
        return (bool) Setting::get("int_{$provider}_enabled", false) && filled(self::key($provider)) && filled(self::secret($provider));
    }

    public static function enabledFor(Platform $platform): bool
    {
        $p = self::providerFor($platform);

        return $p !== null && self::enabled($p);
    }

    public static function key(string $provider): ?string
    {
        return Setting::get("int_{$provider}_key");
    }

    public static function secret(string $provider): ?string
    {
        $v = Setting::get("int_{$provider}_secret");
        if (! $v) {
            return null;
        }
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function save(string $provider, bool $enabled, ?string $key, ?string $secret): void
    {
        Setting::put("int_{$provider}_enabled", $enabled ? '1' : '0');
        if ($key !== null) {
            Setting::put("int_{$provider}_key", trim($key));
        }
        if (filled($secret)) {
            Setting::put("int_{$provider}_secret", Crypt::encryptString(trim($secret)));
        }
    }

    /** Meta Ads (Marketing API): token użytkownika systemowego z uprawnieniem ads_read. */
    public static function metaAdsToken(): ?string
    {
        $v = Setting::get('int_meta_ads_token');
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function metaAdsEnabled(): bool
    {
        return (bool) Setting::get('int_meta_ads_enabled', false) && filled(self::metaAdsToken());
    }

    public static function saveMetaAds(bool $enabled, ?string $token): void
    {
        Setting::put('int_meta_ads_enabled', $enabled ? '1' : '0');
        if (filled($token)) {
            Setting::put('int_meta_ads_token', Crypt::encryptString(trim($token)));
        }
    }

    /** Zaszyfrowane ustawienie (tokeny i klucze API reklam). */
    public static function encrypted(string $key): ?string
    {
        $v = Setting::get($key);
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function putEncrypted(string $key, ?string $value): void
    {
        if (filled($value)) {
            Setting::put($key, Crypt::encryptString(trim($value)));
        }
    }

    /** Google Ads API: token dewelopera, klient OAuth, token odświeżania, opcjonalnie konto menedżera (MCC). */
    public static function googleAds(): array
    {
        return [
            'enabled' => (bool) Setting::get('int_gads_enabled', false),
            'developer_token' => self::encrypted('int_gads_dev_token'), 'client_id' => Setting::get('int_gads_client_id'),
            'client_secret' => self::encrypted('int_gads_client_secret'), 'refresh_token' => self::encrypted('int_gads_refresh_token'),
            'login_customer_id' => Setting::get('int_gads_login_customer'), 'version' => Setting::get('int_gads_version') ?: 'v21',
        ];
    }

    public static function googleAdsEnabled(): bool
    {
        $c = self::googleAds();

        return $c['enabled'] && filled($c['developer_token']) && filled($c['client_id']) && filled($c['client_secret']) && filled($c['refresh_token']);
    }

    /** TikTok Ads (Marketing API): długoterminowy token dostępu do kont reklamowych. */
    public static function tiktokAdsEnabled(): bool
    {
        return (bool) Setting::get('int_ttads_enabled', false) && filled(self::encrypted('int_ttads_token'));
    }

    /** Automatyczne pobieranie statystyk opublikowanych postów (osobny włącznik). */
    public static function autoStats(): bool
    {
        return (bool) Setting::get('int_auto_stats', true);
    }

    /** Widoczność postów TikTok (aplikacja przed audytem TikToka może publikować tylko prywatnie: SELF_ONLY). */
    public static function tiktokPrivacy(): string
    {
        return Setting::get('int_tiktok_privacy', 'SELF_ONLY');
    }

    public static function redirectUri(string $provider): string
    {
        return route('social.connect.callback', $provider);
    }
}
