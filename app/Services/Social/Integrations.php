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
