<?php

namespace App\Services\Social;

use App\Models\AdCampaign;
use App\Models\Campaign;

/**
 * Menedżer reklam Meta (Marketing API): wyniki reklamy i struktura odbiorców kampanii.
 * Reklama w panelu wskazuje ID kampanii (albo zestawu/reklamy) z Meta. Zapis i harmonogram: App\\Services\\Ads\\AdsSync.
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

    /** Wyniki reklamy z menedżera reklam Meta (za cały okres) albo null, gdy brak danych. */
    public function adTotals(AdCampaign $ad): ?array
    {
        $row = $this->meta->adInsights($ad->external_id, Integrations::metaAdsToken())[0] ?? null;
        if (! $row) {
            return null;
        }
        $actions = $this->actions($row['actions'] ?? []);
        $values = $this->actions($row['action_values'] ?? []);

        return [
            'spend' => (float) ($row['spend'] ?? 0), 'impressions' => (int) ($row['impressions'] ?? 0), 'reach' => (int) ($row['reach'] ?? 0),
            'clicks' => (int) ($row['inline_link_clicks'] ?? $row['clicks'] ?? 0),
            'landing_views' => (int) $actions['landing_views'], 'add_to_cart' => (int) $actions['add_to_cart'],
            'conversions' => (int) $actions['conversions'], 'leads' => (int) $actions['leads'], 'revenue' => round($values['conversions'], 2),
        ];
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
