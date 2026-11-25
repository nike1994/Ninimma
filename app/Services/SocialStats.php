<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Dane pulpitu social media dla okresu i (opcjonalnie) jednego konta. */
class SocialStats
{
    public const PERIODS = ['7d', '30d', 'month', 'prev_month'];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public CarbonImmutable $prevFrom;

    public CarbonImmutable $prevTo;

    private ?Collection $current = null;

    private ?Collection $previous = null;

    public function __construct(public string $period = '30d', public ?int $clientId = null)
    {
        $now = CarbonImmutable::now();
        [$this->from, $this->to] = match ($period) {
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'prev_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        $days = (int) $this->from->diffInDays($this->to) + 1;
        $this->prevTo = $this->from->subSecond();
        $this->prevFrom = $this->prevTo->subDays($days - 1)->startOfDay();
    }

    private function published(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Publication::with(['latestMeasurement', 'account'])
            ->where('status', 'published')->whereBetween('published_at', [$from, $to])
            ->when($this->clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $this->clientId)))
            ->get();
    }

    public function current(): Collection
    {
        return $this->current ??= $this->published($this->from, $this->to);
    }

    public function previous(): Collection
    {
        return $this->previous ??= $this->published($this->prevFrom, $this->prevTo);
    }

    private static function sum(Collection $pubs, string $field): int
    {
        return (int) $pubs->sum(fn (Publication $p) => $field === 'engagement' ? $p->engagement() : ($p->latestMeasurement?->{$field} ?? 0));
    }

    public function kpis(): array
    {
        $cur = $this->current();
        $prev = $this->previous();
        $kpi = fn ($icon, $label, $c, $p) => ['icon' => $icon, 'label' => $label, 'value' => $c, 'trend' => DashboardStats::trend($c, $p)];

        return [
            $kpi('bar-chart', __('social.kpi_posts'), $cur->count(), $prev->count()),
            $kpi('users', __('social.kpi_reach'), self::sum($cur, 'reach'), self::sum($prev, 'reach')),
            $kpi('heart', __('social.kpi_engagement'), self::sum($cur, 'engagement'), self::sum($prev, 'engagement')),
            $kpi('cursor', __('social.kpi_clicks'), self::sum($cur, 'clicks'), self::sum($prev, 'clicks')),
        ];
    }

    public function funnel(): array
    {
        $contents = Content::query()
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');
        $scheduled = Publication::where('status', 'planned')->where('scheduled_at', '>=', now())
            ->when($this->clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $this->clientId)))->count();

        return [
            'idea' => (int) ($contents['idea'] ?? 0),
            'preparing' => (int) ($contents['preparing'] ?? 0),
            'scheduled' => $scheduled,
            'published' => $this->current()->count(),
        ];
    }

    /** Publikacje tygodnia do kalendarza godzinowego. */
    public function week(CarbonImmutable $start): Collection
    {
        return Publication::with(['content.files.file', 'account'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$start, $start->addDays(6)->endOfDay()])
            ->when($this->clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $this->clientId)))
            ->orderBy('scheduled_at')->get();
    }

    /** Wyniki według marek (klientów) z trendem względem poprzedniego okresu. */
    public function byBrand(Collection $accounts): Collection
    {
        $cur = $this->current()->groupBy(fn ($p) => $p->account->client_id);
        $prev = $this->previous()->groupBy(fn ($p) => $p->account->client_id);

        return $accounts->groupBy('client_id')->map(function (Collection $accs, $clientId) use ($cur, $prev) {
            $c = $cur[$clientId] ?? collect();
            $p = $prev[$clientId] ?? collect();

            return (object) [
                'client' => $accs->first()->client,
                'accounts' => $accs,
                'reach' => self::sum($c, 'reach'),
                'reach_trend' => DashboardStats::trend(self::sum($c, 'reach'), self::sum($p, 'reach')),
                'engagement' => self::sum($c, 'engagement'),
                'engagement_trend' => DashboardStats::trend(self::sum($c, 'engagement'), self::sum($p, 'engagement')),
                'posts' => $c->count(),
            ];
        })->sortByDesc('reach')->values();
    }

    /** Zaangażowanie narastająco dzień po dniu dla najlepszych marek (wykres liniowy). */
    public function engagementTrend(Collection $brandRows, int $series = 3): array
    {
        $days = [];
        for ($d = $this->from; $d <= $this->to && $d <= CarbonImmutable::now()->endOfDay(); $d = $d->addDay()) {
            $days[] = $d;
        }
        $colors = ['#36b45c', '#e0457b', '#1a9fd8', '#7b55d6'];
        $byBrandDay = $this->current()->groupBy(fn ($p) => $p->account->client_id.'|'.$p->published_at->toDateString());

        $lines = $brandRows->take($series)->values()->map(function ($row, $i) use ($days, $byBrandDay, $colors) {
            $sum = 0;
            $values = [];
            foreach ($days as $d) {
                $sum += (int) ($byBrandDay[$row->client->id.'|'.$d->toDateString()] ?? collect())->sum(fn ($p) => $p->engagement());
                $values[] = $sum;
            }

            return ['name' => $row->client->name, 'color' => $colors[$i % 4], 'values' => $values];
        })->all();

        return [
            'labels' => array_map(fn ($d) => $d->translatedFormat(count($days) <= 7 ? 'D' : 'j M'), $days),
            'titles' => array_map(fn ($d) => $d->translatedFormat('D, j M'), $days),
            'lines' => $lines,
        ];
    }

    /** Udział platform w zasięgu (wykres kołowy). */
    public function platformShares(): Collection
    {
        $total = max(1, self::sum($this->current(), 'reach'));

        return $this->current()->groupBy(fn ($p) => $p->platform->value)
            ->map(fn ($pubs, $platform) => (object) [
                'platform' => Platform::from($platform),
                'reach' => self::sum($pubs, 'reach'),
                'share' => round(self::sum($pubs, 'reach') / $total * 100),
            ])->sortByDesc('reach')->values();
    }

    public function totalReach(): int
    {
        return self::sum($this->current(), 'reach');
    }

    /** Publikacje czekające na wpisanie statystyk (kolejka po 1, 7, 30 dniach). */
    public static function statsQueue(?int $clientId = null): Collection
    {
        return Publication::with(['content', 'account', 'latestMeasurement'])->dueForCheck()
            ->when($clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $clientId)))
            ->orderBy('published_at')->get()
            ->filter(fn (Publication $p) => $p->nextCheckAt() && $p->nextCheckAt()->lte(now()))->values();
    }
}
