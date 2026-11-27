<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdResult;
use App\Models\Campaign;
use App\Services\Social\Integrations;
use App\Services\Social\MetaAdsSync;
use Illuminate\Support\Collection;

/**
 * Automatyczne wyniki reklam z menedżerów reklam: Meta, Google Ads, TikTok Ads (każda platforma ma własny włącznik
 * w Administracji → Integracje). Reklama musi mieć ID kampanii z menedżera. Wynik = pomiar narastający z dziś.
 */
class AdsSync
{
    public function __construct(private MetaAdsSync $meta, private GoogleAdsClient $google, private TikTokAdsClient $tiktok) {}

    /** Platformy reklam z włączoną integracją. */
    public static function platforms(): array
    {
        return array_keys(array_filter([
            'meta' => Integrations::metaAdsEnabled(), 'google' => Integrations::googleAdsEnabled(), 'tiktok' => Integrations::tiktokAdsEnabled(),
        ]));
    }

    public static function enabled(): bool
    {
        return self::platforms() !== [];
    }

    /** Cron: reklamy z ID, które trwają lub skończyły się niedawno (i jeszcze niepobrane). */
    public function run(): int
    {
        $platforms = self::platforms();
        if (! $platforms) {
            return 0;
        }
        $ads = AdCampaign::whereIn('platform', $platforms)->whereNotNull('external_id')
            ->where(fn ($q) => $q->whereIn('status', ['planned', 'active'])->orWhereNull('synced_at')
                ->orWhereDate('ends_on', '>=', today()->subDays(3)))->get();

        return $this->syncAds($ads);
    }

    /** Przycisk przy kampanii: wszystkie jej reklamy z ID na włączonych platformach (+ odbiorcy z Meta). */
    public function syncCampaign(Campaign $campaign): array
    {
        $ads = $campaign->ads()->whereIn('platform', self::platforms())->whereNotNull('external_id')->get();
        $ok = $this->syncAds($ads, withAudience: false);
        $audience = $ok > 0 && $ads->contains('platform', 'meta') && $this->meta->syncAudience($campaign);

        return ['ads' => $ads->count(), 'ok' => $ok, 'audience' => $audience,
            'errors' => $ads->map->fresh()->pluck('sync_error')->filter()->unique()->values()->all()];
    }

    private function syncAds(Collection $ads, bool $withAudience = true): int
    {
        $ok = $ads->filter(fn ($a) => $this->syncAd($a))->count();
        if ($withAudience) {
            $ids = $ads->where('platform', 'meta')->pluck('campaign_id')->filter()->unique();
            Campaign::whereIn('id', $ids)->get()->each(fn ($c) => $this->meta->syncAudience($c));
        }

        return $ok;
    }

    public function syncAd(AdCampaign $ad): bool
    {
        try {
            $vals = match ($ad->platform) {
                'meta' => $this->meta->adTotals($ad),
                'google' => $this->google->adTotals($ad),
                'tiktok' => $this->tiktok->adTotals($ad),
                default => null,
            };
            if ($vals) {
                AdResult::storeDaily($ad, $vals, $ad->platform);
            }
            $ad->forceFill(['synced_at' => now(), 'sync_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $ad->forceFill(['sync_error' => mb_substr($e->getMessage(), 0, 300)])->save();
            report($e);

            return false;
        }
    }
}
