<?php

namespace App\Services\Social;

use App\Models\AdCampaign;
use App\Models\AdResult;
use App\Models\Campaign;

/**
 * Pobieranie wyników reklam i struktury odbiorców z menedżera reklam Meta (Marketing API).
 * Reklama w panelu wskazuje ID kampanii (albo zestawu/reklamy) z Meta; wynik zapisujemy jako pomiar narastający z dziś.
 */
class MetaAdsSync
{
    /** Typy akcji Meta w kolejności ważności (bierzemy pierwszy obecny — warianty się dublują). */
    private const ACTIONS = [
        'landing_views' => ['landing_page_view'],
        'add_to_cart' => ['omni_add_to_cart', 'add_to_cart', 'offsite_conversion.fb_pixel_add_to_cart'],
        'conversions' => ['omni_purchase', 'purchase', 'offsite_conversion.fb_pixel_purchase'],
        'leads' => ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead'],
    ];

    /** Województwa w nazwach z Meta → nazwy polskie (pokazywane na mapie). */
    public const REGIONS = [
        'Masovian' => 'Mazowieckie', 'Lesser Poland' => 'Małopolskie', 'Silesian' => 'Śląskie', 'Lower Silesian' => 'Dolnośląskie',
        'Greater Poland' => 'Wielkopolskie', 'Pomeranian' => 'Pomorskie', 'Łódź' => 'Łódzkie', 'Lodz' => 'Łódzkie',
        'West Pomeranian' => 'Zachodniopomorskie', 'Kuyavian-Pomeranian' => 'Kujawsko-pomorskie', 'Lublin' => 'Lubelskie',
        'Subcarpathian' => 'Podkarpackie', 'Podlaskie' => 'Podlaskie', 'Warmian-Masurian' => 'Warmińsko-mazurskie',
        'Lubusz' => 'Lubuskie', 'Opole' => 'Opolskie', 'Świętokrzyskie' => 'Świętokrzyskie', 'Swietokrzyskie' => 'Świętokrzyskie',
    ];

    public function __construct(private MetaClient $meta) {}

    public static function enabled(): bool
    {
        return Integrations::metaAdsEnabled();
    }

    /** Cron: reklamy Meta z ID, które trwają lub skończyły się niedawno. Zwraca liczbę zsynchronizowanych. */
    public function run(): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $ads = AdCampaign::where('platform', 'meta')->whereNotNull('external_id')
            ->where(fn ($q) => $q->whereIn('status', ['planned', 'active'])->orWhereNull('synced_at')
                ->orWhereDate('ends_on', '>=', today()->subDays(3)))->get();
        $ok = $ads->filter(fn ($a) => $this->syncAd($a))->count();
        Campaign::whereIn('id', $ads->pluck('campaign_id')->filter()->unique())->get()->each(fn ($c) => $this->syncAudience($c));

        return $ok;
    }

    /** Synchronizacja jednej kampanii z panelu (przycisk): wszystkie jej reklamy Meta + odbiorcy. */
    public function syncCampaign(Campaign $campaign): array
    {
        $ads = $campaign->ads()->where('platform', 'meta')->whereNotNull('external_id')->get();
        $ok = $ads->filter(fn ($a) => $this->syncAd($a))->count();
        $audience = $ok > 0 && $this->syncAudience($campaign);

        return ['ads' => $ads->count(), 'ok' => $ok, 'audience' => $audience, 'errors' => $ads->pluck('sync_error')->filter()->unique()->values()->all()];
    }

    public function syncAd(AdCampaign $ad): bool
    {
        try {
            $row = $this->meta->adInsights($ad->external_id, Integrations::metaAdsToken())[0] ?? null;
            if ($row) {
                $actions = $this->actions($row['actions'] ?? []);
                $values = $this->actions($row['action_values'] ?? []);
                $vals = [
                    'spend' => (float) ($row['spend'] ?? 0), 'impressions' => (int) ($row['impressions'] ?? 0), 'reach' => (int) ($row['reach'] ?? 0),
                    'clicks' => (int) ($row['inline_link_clicks'] ?? $row['clicks'] ?? 0),
                    'landing_views' => (int) $actions['landing_views'], 'add_to_cart' => (int) $actions['add_to_cart'],
                    'conversions' => (int) $actions['conversions'], 'leads' => (int) $actions['leads'], 'revenue' => round($values['conversions'], 2),
                ];
                // Jeden pomiar narastający dziennie — kolejne pobranie tego samego dnia go nadpisuje.
                $existing = AdResult::where('ad_campaign_id', $ad->id)->where('source', 'meta')->whereDate('measured_on', today())->first();
                $existing ? $existing->update($vals) : AdResult::create($vals + ['ad_campaign_id' => $ad->id, 'measured_on' => today(), 'source' => 'meta']);
            }
            $ad->forceFill(['synced_at' => now(), 'sync_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $ad->forceFill(['sync_error' => mb_substr($e->getMessage(), 0, 300)])->save();
            report($e);

            return false;
        }
    }

    /** Odbiorcy kampanii z podziałów Meta (wiek+płeć, region, kraj), zsumowane po wszystkich reklamach. */
    public function syncAudience(Campaign $campaign): bool
    {
        $ads = $campaign->ads()->where('platform', 'meta')->whereNotNull('external_id')->get();
        if ($ads->isEmpty()) {
            return false;
        }
        $sum = ['age' => [], 'gender' => [], 'city' => [], 'country' => []];
        try {
            foreach ($ads as $ad) {
                foreach ($this->meta->adInsights($ad->external_id, Integrations::metaAdsToken(), 'age,gender') as $r) {
                    $v = $this->size($r);
                    if (in_array($r['gender'] ?? '', ['female', 'male'], true)) {
                        $sum['gender'][$r['gender']] = ($sum['gender'][$r['gender']] ?? 0) + $v;
                    }
                    if (isset($r['age']) && $r['age'] !== 'Unknown') {
                        $sum['age'][$r['age']] = ($sum['age'][$r['age']] ?? 0) + $v;
                    }
                }
                foreach ($this->meta->adInsights($ad->external_id, Integrations::metaAdsToken(), 'region') as $r) {
                    $label = self::regionName((string) ($r['region'] ?? ''));
                    if ($label !== '' && $label !== 'Unknown') {
                        $sum['city'][$label] = ($sum['city'][$label] ?? 0) + $this->size($r);
                    }
                }
                foreach ($this->meta->adInsights($ad->external_id, Integrations::metaAdsToken(), 'country') as $r) {
                    $label = self::countryName((string) ($r['country'] ?? ''));
                    if ($label !== '') {
                        $sum['country'][$label] = ($sum['country'][$label] ?? 0) + $this->size($r);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        $rows = [];
        foreach ($sum as $dim => $values) {
            $total = array_sum($values);
            if ($total <= 0) {
                continue;
            }
            arsort($values);
            foreach (array_slice($values, 0, $dim === 'age' || $dim === 'gender' ? 10 : 12, true) as $label => $v) {
                $rows[] = [$dim, (string) $label, $v / $total * 100];
            }
        }
        if (! $rows) {
            return false;
        }
        $campaign->saveAudience($rows, array_values(array_unique(array_column($rows, 0))), 'meta');

        return true;
    }

    public static function regionName(string $region): string
    {
        $base = trim(preg_replace('/\s+(Voivodeship|Province|Region)$/i', '', trim($region)));

        return self::REGIONS[$base] ?? $base;
    }

    public static function countryName(string $code): string
    {
        $code = strtoupper(trim($code));
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return $code;
        }
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, app()->getLocale()) : '';

        return $name !== '' && $name !== $code ? mb_convert_case(mb_substr($name, 0, 1), MB_CASE_UPPER).mb_substr($name, 1) : $code;
    }

    private function size(array $r): int
    {
        return (int) ($r['reach'] ?? 0) ?: (int) ($r['impressions'] ?? 0);
    }

    private function actions(array $list): array
    {
        $by = collect($list)->mapWithKeys(fn ($a) => [$a['action_type'] ?? '' => (float) ($a['value'] ?? 0)]);
        $out = [];
        foreach (self::ACTIONS as $key => $types) {
            $out[$key] = 0;
            foreach ($types as $t) {
                if ($by->has($t)) {
                    $out[$key] = $by[$t];
                    break;
                }
            }
        }

        return $out;
    }
}
