<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\CampaignAudience;
use App\Models\FileUsage;
use App\Models\Publication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Analityka kampanii marketingowej: wyniki (organiczne + płatne), przebieg w czasie, platformy,
 * lejek, najlepsze kreacje i struktura odbiorców. Pomiary są narastające — liczymy ostatni stan na dzień.
 */
class CampaignInsights
{
    private ?Collection $pubs = null;

    private ?Collection $ads = null;

    public function __construct(public Campaign $campaign) {}

    public function publications(): Collection
    {
        return $this->pubs ??= (new CampaignReport($this->campaign))->publications()
            ->load(['measurements', 'content.files.file', 'account']);
    }

    public function ads(): Collection
    {
        return $this->ads ??= $this->campaign->ads()->with(['results', 'latestResult', 'publication.content.files.file', 'material.file.file'])->get();
    }

    /** Wyniki kampanii: zasięg, interakcje, kliknięcia, CTR, wydatki, konwersje, przychód, ROAS. */
    public function totals(): array
    {
        $org = ['reach' => 0, 'engagement' => 0, 'clicks' => 0];
        foreach ($this->publications() as $p) {
            $m = $p->measurements->sortByDesc('measured_on')->first();
            $org['reach'] += (int) ($m->reach ?? 0);
            $org['engagement'] += $m ? $m->reactions + $m->comments + $m->shares : 0;
            $org['clicks'] += (int) ($m->clicks ?? 0);
        }
        $paid = AdService::aggregate($this->ads());
        $funnel = $this->ads()->reduce(fn ($c, $a) => ['landing_views' => $c['landing_views'] + $a->totals()['landing_views'], 'add_to_cart' => $c['add_to_cart'] + $a->totals()['add_to_cart']], ['landing_views' => 0, 'add_to_cart' => 0]);
        $reach = $org['reach'] + $paid['reach'];
        $impressions = $paid['impressions'] + $org['reach'];
        $clicks = $org['clicks'] + $paid['clicks'];
        $revenue = (float) $paid['revenue'] + (float) ($this->campaign->revenue ?? 0);
        $spent = $this->campaign->spent();

        return [
            'reach' => $reach, 'engagement' => $org['engagement'], 'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : null,
            'impressions' => $impressions, 'spend' => $spent, 'ad_spend' => (float) $paid['spend'],
            'conversions' => $paid['conversions'] + $paid['leads'] + (int) ($this->campaign->leads ?? 0),
            'purchases' => (int) $paid['conversions'], 'leads' => (int) $paid['leads'] + (int) ($this->campaign->leads ?? 0),
            'paid_reach' => (int) $paid['reach'], 'paid_impressions' => (int) $paid['impressions'], 'paid_clicks' => (int) $paid['clicks'],
            'landing_views' => $funnel['landing_views'], 'add_to_cart' => $funnel['add_to_cart'],
            'revenue' => $revenue, 'roas' => $spent > 0 && $revenue > 0 ? round($revenue / $spent, 1) : null,
        ];
    }

    /** Dni kampanii do wykresu: od startu do końca (albo dziś), najwyżej ok. 60 punktów. */
    private function days(): Collection
    {
        $start = CarbonImmutable::parse($this->campaign->starts_on ?? $this->campaign->created_at)->startOfDay();
        $end = CarbonImmutable::parse(min($this->campaign->ends_on ?? today(), today()))->startOfDay();
        if ($end->lt($start)) {
            $end = $start;
        }
        $total = $start->diffInDays($end) + 1;
        $step = max(1, (int) ceil($total / 60));
        $days = collect();
        for ($d = $start; $d->lte($end); $d = $d->addDays($step)) {
            $days->push($d);
        }
        if (! $days->last()->equalTo($end)) {
            $days->push($end);
        }

        return $days;
    }

    /** Narastający zasięg i kliknięcia na kolejne dni (ostatni pomiar ≤ dnia, organiczne + płatne). */
    public function series(): array
    {
        $out = ['labels' => [], 'reach' => [], 'clicks' => []];
        foreach ($this->days() as $d) {
            $reach = 0;
            $clicks = 0;
            foreach ($this->publications() as $p) {
                $m = $p->measurements->filter(fn ($x) => $x->measured_on->lte($d))->sortByDesc('measured_on')->first();
                $reach += (int) ($m->reach ?? 0);
                $clicks += (int) ($m->clicks ?? 0);
            }
            foreach ($this->ads() as $a) {
                $r = $a->results->filter(fn ($x) => $x->measured_on->lte($d))->sortByDesc('measured_on')->first();
                $reach += (int) ($r->reach ?? 0);
                $clicks += (int) ($r->clicks ?? 0);
            }
            $out['labels'][] = $d;
            $out['reach'][] = $reach;
            $out['clicks'][] = $clicks;
        }

        return $out;
    }

    /** Zmiana w ostatnich 7 dniach względem 7 dni wcześniej (przyrost zasięgu i kliknięć), w %. */
    public function trends(): array
    {
        $at = fn (CarbonImmutable $d) => $this->stateAt($d);
        $now = CarbonImmutable::parse(min($this->campaign->ends_on ?? today(), today()));
        [$a, $b, $c] = [$at($now), $at($now->subDays(7)), $at($now->subDays(14))];
        $pct = fn ($cur, $prev) => $prev > 0 ? (int) round(($cur - $prev) / $prev * 100) : null;

        return [
            'reach' => $pct($a['reach'] - $b['reach'], $b['reach'] - $c['reach']),
            'clicks' => $pct($a['clicks'] - $b['clicks'], $b['clicks'] - $c['clicks']),
            'engagement' => $pct($a['engagement'] - $b['engagement'], $b['engagement'] - $c['engagement']),
        ];
    }

    public function stateAt(CarbonImmutable $d): array
    {
        $s = ['reach' => 0, 'clicks' => 0, 'engagement' => 0];
        foreach ($this->publications() as $p) {
            $m = $p->measurements->filter(fn ($x) => $x->measured_on->lte($d))->sortByDesc('measured_on')->first();
            $s['reach'] += (int) ($m->reach ?? 0);
            $s['clicks'] += (int) ($m->clicks ?? 0);
            $s['engagement'] += $m ? $m->reactions + $m->comments + $m->shares : 0;
        }
        foreach ($this->ads() as $a) {
            $r = $a->results->filter(fn ($x) => $x->measured_on->lte($d))->sortByDesc('measured_on')->first();
            $s['reach'] += (int) ($r->reach ?? 0);
            $s['clicks'] += (int) ($r->clicks ?? 0);
        }

        return $s;
    }

    /** Zasięg i kliknięcia wg platformy (posty wg konta, reklamy wg menedżera reklam). */
    public function platforms(): Collection
    {
        $rows = [];
        foreach ($this->publications() as $p) {
            $k = $p->platform->value;
            $m = $p->measurements->sortByDesc('measured_on')->first();
            $rows[$k] ??= ['key' => $k, 'reach' => 0, 'clicks' => 0];
            $rows[$k]['reach'] += (int) ($m->reach ?? 0);
            $rows[$k]['clicks'] += (int) ($m->clicks ?? 0);
        }
        foreach ($this->ads() as $a) {
            // Promowany post liczy się do platformy posta; reklama Meta bez posta — do Facebooka.
            $k = $a->publication?->platform->value ?? match ($a->platform) { 'meta' => 'facebook', 'google' => 'google', default => $a->platform };
            $t = $a->totals();
            $rows[$k] ??= ['key' => $k, 'reach' => 0, 'clicks' => 0];
            $rows[$k]['reach'] += $t['reach'];
            $rows[$k]['clicks'] += $t['clicks'];
        }

        return collect($rows)->map(fn ($r) => (object) ($r + ['platform' => Platform::tryFrom($r['key'])]))->sortByDesc('reach')->values();
    }

    public function objective(): string
    {
        return $this->campaign->objectiveKey();
    }

    /** Główny wynik wg celu kampanii i postęp względem wartości docelowej. */
    public function goal(array $t): object
    {
        $key = $this->objective();
        [$metric, $value, $format] = match ($key) {
            'sales' => ['revenue', (float) $t['revenue'], 'money'],
            'leads' => ['leads', $t['leads'] ?: $t['conversions'], 'num'],
            'traffic' => ['visits', $t['landing_views'] ?: $t['clicks'], 'num'],
            'engagement' => ['engagement', $t['engagement'], 'num'],
            default => ['reach', $t['reach'], 'num'],
        };
        $target = (float) ($this->campaign->target_value ?? 0);

        return (object) ['objective' => $key, 'metric' => $metric, 'value' => $value, 'format' => $format,
            'target' => $target > 0 ? $target : null, 'pct' => $target > 0 ? (int) round($value / $target * 100) : null];
    }

    /** Cztery wskaźniki panelu „Wyniki kampanii” dobrane do celu: [klucz, wartość, format]. */
    public function metrics(array $t): array
    {
        $div = fn ($a, $b, $m = 1) => $b > 0 ? round($a / $b * $m, 2) : null;
        $leads = $t['leads'] ?: $t['conversions'];
        $visits = $t['landing_views'] ?: $t['clicks'];
        $rows = match ($this->objective()) {
            'sales' => [['revenue', $t['revenue'], 'money'], ['roas', $t['roas'], 'x'], ['purchases', $t['purchases'] ?: $t['conversions'], 'num'],
                ['cost_per_purchase', $div($t['spend'], $t['purchases'] ?: $t['conversions']), 'money']],
            'leads' => [['leads', $leads, 'num'], ['cpl', $div($t['spend'], $leads), 'money'], ['clicks', $t['clicks'], 'num'],
                ['lead_rate', $div($leads, $visits, 100), 'pct']],
            'traffic' => [['visits', $visits, 'num'], ['cpc', $div($t['ad_spend'], $t['paid_clicks']), 'money'], ['ctr', $t['ctr'], 'pct'], ['reach', $t['reach'], 'num']],
            'engagement' => [['engagement', $t['engagement'], 'num'], ['cpe', $div($t['spend'], $t['engagement']), 'money'], ['reach', $t['reach'], 'num'],
                ['eng_rate', $div($t['engagement'], $t['reach'], 100), 'pct']],
            default => [['reach', $t['reach'], 'num'], ['frequency', $div($t['paid_impressions'], $t['paid_reach']), 'freq'],
                ['cpm', $div($t['ad_spend'], $t['paid_impressions'], 1000), 'money'], ['engagement', $t['engagement'], 'num']],
        };

        return array_map(fn ($r) => (object) ['key' => $r[0], 'value' => $r[1], 'format' => $r[2]], $rows);
    }

    /** Lejek wg celu (sprzedaż: koszyk i zakup; leady: lead; ruch: kliknięcia i wejścia). Zasięg/zaangażowanie — brak lejka. */
    public function funnel(array $t): Collection
    {
        $keys = match ($this->objective()) {
            'sales' => ['reach' => $t['reach'], 'visits' => $t['landing_views'] ?: $t['clicks'], 'cart' => $t['add_to_cart'], 'purchase' => $t['purchases'] ?: $t['conversions']],
            'leads' => ['reach' => $t['reach'], 'visits' => $t['landing_views'] ?: $t['clicks'], 'lead' => $t['leads'] ?: $t['conversions']],
            'traffic' => ['reach' => $t['reach'], 'clicks' => $t['clicks'], 'visits' => $t['landing_views']],
            default => [],
        };
        $steps = collect($keys)->map(fn ($v, $k) => ['key' => $k, 'value' => (int) $v])->filter(fn ($s) => $s['value'] > 0)->values();
        if ($steps->count() < 2) {
            return collect();
        }
        $first = $steps->first()['value'];

        return $steps->map(function ($s, $i) use ($steps, $first) {
            $prev = $i > 0 ? $steps[$i - 1]['value'] : null;

            return (object) ($s + [
                'of_total' => round($s['value'] / $first * 100, 2),
                'step_rate' => $prev ? round($s['value'] / $prev * 100, 1) : null,
            ]);
        });
    }

    /**
     * Tempo wydawania budżetu reklam względem upływu czasu kampanii. Liczymy na reklamach (opłaty dla
     * influencerów są zwykle jednorazowe na starcie i zafałszowałyby tempo).
     * state: over (za szybko — budżet skończy się przed końcem), under (za wolno), ok.
     */
    public function pacing(array $t): ?object
    {
        $c = $this->campaign;
        if ($c->status !== 'active' || ! $c->starts_on || ! $c->ends_on || $this->ads()->isEmpty() || today()->lt($c->starts_on)) {
            return null;
        }
        $adBudget = (float) $this->ads()->sum('budget') ?: max(0, (float) $c->budget - ($t['spend'] - $t['ad_spend']));
        if ($adBudget <= 0) {
            return null;
        }
        $total = (int) $c->starts_on->diffInDays($c->ends_on) + 1;
        $elapsed = min($total, (int) $c->starts_on->diffInDays(today()) + 1);
        $spent = (float) $t['ad_spend'];
        $timePct = $elapsed / $total * 100;
        $spendPct = $spent / $adBudget * 100;
        $projected = $elapsed > 0 ? $spent / $elapsed * $total : 0;
        $state = match (true) {
            $spendPct >= 100 && $elapsed < $total => 'over',
            $elapsed >= 3 && $spendPct > $timePct + 15 && $projected > $adBudget * 1.1 => 'over',
            $timePct >= 40 && $spendPct < $timePct * 0.6 => 'under',
            default => 'ok',
        };

        return (object) ['state' => $state, 'budget' => $adBudget, 'spent' => $spent, 'time_pct' => (int) round($timePct),
            'spend_pct' => (int) round($spendPct), 'projected' => round($projected, 2), 'days_left' => $total - $elapsed];
    }

    /** Najlepsze kreacje: posty i reklamy wg CTR (kliknięcia / wyświetlenia), potem kliknięcia. */
    public function creatives(int $limit = 3): Collection
    {
        $items = collect();
        foreach ($this->publications() as $p) {
            $m = $p->measurements->sortByDesc('measured_on')->first();
            if (! $m || ! $m->reach) {
                continue;
            }
            $items->push((object) ['name' => $p->content->title, 'sub' => $p->platform->label(), 'clicks' => (int) $m->clicks,
                'ctr' => round($m->clicks / $m->reach * 100, 2), 'thumb' => $p->content->thumbnail(), 'url' => route('social.contents.edit', $p->content_id)]);
        }
        foreach ($this->ads() as $a) {
            $t = $a->totals();
            if (! $t['impressions']) {
                continue;
            }
            $thumb = $a->publication?->content?->thumbnail() ?? ($a->material?->file?->file?->isImage() ? $a->material->file : null);
            $items->push((object) ['name' => $a->name, 'sub' => __('ads.platform_'.$a->platform), 'clicks' => $t['clicks'],
                'ctr' => round($t['clicks'] / $t['impressions'] * 100, 2), 'thumb' => $thumb, 'url' => route('social.ads.edit', $a)]);
        }

        return $items->sortBy([['ctr', 'desc'], ['clicks', 'desc']])->take($limit)->values();
    }

    /** Struktura odbiorców: [gender => [label => %], age => [...], city => [...], country => [...]]. */
    public function audience(): array
    {
        $rows = $this->campaign->relationLoaded('audiences') ? $this->campaign->audiences : $this->campaign->audiences()->get();
        $out = array_fill_keys(CampaignAudience::DIMENSIONS, []);
        foreach ($rows->sortByDesc('share') as $r) {
            $out[$r->dimension][$r->label] = $r->share;
        }
        // Wiek w stałej kolejności przedziałów.
        $age = [];
        foreach (CampaignAudience::AGES as $a) {
            if (isset($out['age'][$a])) {
                $age[$a] = $out['age'][$a];
            }
        }
        $out['age'] = $age;

        return $out;
    }

    /** Okładka kampanii: pierwsza grafika z treści kampanii albo kreacji reklamy. */
    public function cover(): ?FileUsage
    {
        foreach ($this->campaign->contents()->with('files.file')->latest()->get() as $c) {
            if ($t = $c->thumbnail()) {
                return $t;
            }
        }
        foreach ($this->ads() as $a) {
            if ($t = $a->publication?->content?->thumbnail()) {
                return $t;
            }
        }

        return null;
    }

    /** Platformy kampanii (ikony na kartach): konta publikacji + platformy reklam. */
    public function platformKeys(): Collection
    {
        return $this->publications()->toBase()->map(fn ($p) => $p->platform->value)
            ->merge($this->ads()->map(fn ($a) => $a->publication?->platform->value ?? ($a->platform === 'meta' ? 'facebook' : $a->platform)))
            ->filter(fn ($k) => Platform::tryFrom($k))->unique()->values();
    }
}
