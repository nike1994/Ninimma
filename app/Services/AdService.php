<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdResult;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Marketing płatny: wskaźniki (CTR, CPC, CPM, koszt wyniku, ROAS), import z menedżera reklam, statusy. */
class AdService
{
    /** Wskaźniki dla sumy wyników. */
    public static function metrics(array $t): array
    {
        $div = fn ($a, $b, $m = 1) => $b > 0 ? round($a / $b * $m, 2) : null;
        $results = $t['conversions'] + $t['leads'];

        return $t + [
            'ctr' => $div($t['clicks'], $t['impressions'], 100),
            'cpc' => $div($t['spend'], $t['clicks']),
            'cpm' => $div($t['spend'], $t['impressions'], 1000),
            'cpa' => $div($t['spend'], $results),
            'roas' => $t['revenue'] > 0 ? $div($t['revenue'], $t['spend']) : null,
        ];
    }

    /** Suma wyników wielu reklam (ostatni pomiar każdej) + wskaźniki. */
    public static function aggregate(Collection $ads): array
    {
        $sum = ['spend' => 0.0, 'impressions' => 0, 'reach' => 0, 'clicks' => 0, 'conversions' => 0, 'leads' => 0, 'revenue' => 0.0, 'budget' => 0.0];
        foreach ($ads as $ad) {
            $t = $ad->totals();
            foreach (['spend', 'impressions', 'reach', 'clicks', 'conversions', 'leads', 'revenue'] as $k) {
                $sum[$k] += $t[$k];
            }
            $sum['budget'] += (float) $ad->budget;
        }

        return self::metrics($sum);
    }

    public function addResult(AdCampaign $ad, ?User $by, array $data, string $source = 'manual'): AdResult
    {
        return $ad->results()->create([
            'user_id' => $by?->id, 'measured_on' => $data['measured_on'] ?? today()->toDateString(), 'source' => $source,
            'spend' => (float) ($data['spend'] ?? 0), 'impressions' => (int) ($data['impressions'] ?? 0), 'reach' => (int) ($data['reach'] ?? 0),
            'clicks' => (int) ($data['clicks'] ?? 0), 'conversions' => (int) ($data['conversions'] ?? 0), 'leads' => (int) ($data['leads'] ?? 0),
            'revenue' => (float) ($data['revenue'] ?? 0),
            'landing_views' => (int) ($data['landing_views'] ?? 0), 'add_to_cart' => (int) ($data['add_to_cart'] ?? 0),
        ]);
    }

    /** Kolumny z eksportów Meta / Google / TikTok (PL i EN) → pola wyniku. */
    private const COLUMNS = [
        'name' => ['nazwa kampanii', 'campaign name', 'kampania', 'campaign', 'nazwa zestawu reklam', 'ad set name', 'nazwa reklamy', 'ad name'],
        'date' => ['data zakończenia raportowania', 'reporting ends', 'dzień', 'day', 'data', 'date'],
        'spend' => ['wydana kwota (pln)', 'kwota wydana (pln)', 'amount spent (pln)', 'amount spent', 'wydana kwota', 'koszt', 'cost', 'spend'],
        'impressions' => ['wyświetlenia', 'impressions', 'impr.'],
        'reach' => ['zasięg', 'reach'],
        'clicks' => ['kliknięcia linku', 'link clicks', 'kliknięcia', 'clicks'],
        'conversions' => ['konwersje', 'conversions', 'zakupy', 'purchases', 'wyniki', 'results'],
        'leads' => ['leady', 'leads', 'potencjalni klienci'],
        'landing_views' => ['wyświetlenia strony docelowej', 'landing page views', 'wejścia na stronę', 'sesje', 'sessions'],
        'add_to_cart' => ['dodania do koszyka', 'adds to cart', 'add to cart', 'dodanie do koszyka'],
        'revenue' => ['wartość konwersji', 'conv. value', 'wartość konwersji zakupów', 'purchases conversion value', 'przychód', 'revenue'],
    ];

    private static function number(string $v): float
    {
        $v = str_replace(["\u{00a0}", ' ', 'zł', 'PLN', '%'], '', trim($v));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v); // 1.234,56
        } else {
            $v = str_replace(',', (str_contains($v, '.') ? '' : '.'), $v); // 1,234.56 lub 1234,56
        }

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * Import CSV z menedżera reklam. Wiersze dopasowywane po nazwie (pole „nazwa w menedżerze reklam”, potem nazwa),
     * albo wszystkie do wskazanej reklamy. Zwraca [zapisane, niedopasowane nazwy].
     */
    public function importCsv(string $csv, ?User $by, ?AdCampaign $only = null, ?string $date = null): array
    {
        $lines = preg_split('/\R/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        $sep = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : (substr_count($lines[0] ?? '', "\t") ? "\t" : ',');
        $header = array_map(fn ($h) => mb_strtolower(trim($h, " \"")), str_getcsv(array_shift($lines) ?? '', $sep));
        $map = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($names as $n) {
                if (($i = array_search($n, $header, true)) !== false) {
                    $map[$field] = $i;
                    break;
                }
            }
        }
        $saved = 0;
        $unmatched = [];
        $ads = $only ? collect([$only]) : AdCampaign::all();

        DB::transaction(function () use ($lines, $sep, $map, $ads, $only, $by, $date, &$saved, &$unmatched) {
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $cols = str_getcsv($line, $sep);
                $name = isset($map['name']) ? trim($cols[$map['name']] ?? '') : '';
                $ad = $only ?? $ads->first(fn ($a) => $name !== '' && (mb_strtolower((string) $a->external_name) === mb_strtolower($name) || mb_strtolower($a->name) === mb_strtolower($name)));
                if (! $ad) {
                    if ($name !== '' && ! in_array(mb_strtolower($name), ['total', 'razem', 'suma'], true)) {
                        $unmatched[] = $name;
                    }
                    continue;
                }
                $row = ['measured_on' => $date ?? (isset($map['date']) && strtotime($cols[$map['date']] ?? '') ? Carbon::parse($cols[$map['date']])->toDateString() : today()->toDateString())];
                foreach (['spend', 'impressions', 'reach', 'clicks', 'conversions', 'leads', 'revenue', 'landing_views', 'add_to_cart'] as $f) {
                    $row[$f] = isset($map[$f]) ? self::number((string) ($cols[$map[$f]] ?? '0')) : 0;
                }
                $this->addResult($ad, $by, $row, 'import');
                $saved++;
            }
        });

        return [$saved, array_values(array_unique($unmatched))];
    }

    /** Promowany post (zaznaczenie przy publikacji) = wpis reklamy powiązany z publikacją. */
    public function syncFromPublication(Publication $pub): void
    {
        $pub->loadMissing(['content', 'account']);
        $ad = AdCampaign::where('publication_id', $pub->id)->first();
        if (! $pub->promoted) {
            // Odznaczenie: wpis bez wyników usuwamy, z wynikami zostaje (historia wydatków).
            if ($ad && $ad->results()->doesntExist()) {
                $ad->delete();
            }

            return;
        }
        $platform = in_array($pub->platform->value, ['facebook', 'instagram'], true) ? 'meta' : (in_array($pub->platform->value, AdCampaign::PLATFORMS, true) ? $pub->platform->value : 'other');
        $data = [
            'client_id' => $pub->content->client_id, 'campaign_id' => $pub->content->campaign_id, 'platform' => $platform,
            'budget' => (float) $pub->promo_budget, 'starts_on' => $pub->promo_from ?? $pub->scheduled_at->toDateString(), 'ends_on' => $pub->promo_to,
        ];
        if ($ad) {
            $ad->update($data);
        } else {
            AdCampaign::create($data + ['publication_id' => $pub->id, 'objective' => 'engagement',
                'name' => __('ads.boost_name', ['title' => $pub->content->title, 'platform' => $pub->platform->label()])]);
        }
    }

    /** Cron: planowane → aktywne w dniu startu, aktywne → zakończone po dacie końca. */
    public function updateStatuses(): array
    {
        // Reklama z materiałem po wygaśnięciu licencji nie startuje sama (zostaje jako konflikt do decyzji).
        $started = AdCampaign::where('status', 'planned')->whereNotNull('starts_on')->whereDate('starts_on', '<=', today())
            ->whereDoesntHave('material', fn ($m) => $m->whereNotNull('license_until')->whereDate('license_until', '<', today()))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->update(['status' => 'active']);
        $finished = AdCampaign::whereIn('status', ['planned', 'active'])->whereNotNull('ends_on')->whereDate('ends_on', '<', today())->update(['status' => 'finished']);

        return [$started, $finished];
    }
}
