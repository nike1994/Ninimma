<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Publication;
use App\Models\Setting;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/** Koszty i wyniki kampanii zbierane bez ręcznego liczenia. */
class CampaignReport
{
    public function __construct(public Campaign $campaign)
    {
        $campaign->loadMissing(['collaborations.influencer', 'collaborations.shipments', 'collaborations.materials', 'contents']);
    }

    public static function hourlyRate(): float
    {
        return (float) Setting::get('hourly_rate', 150);
    }

    private function collabs(): Collection
    {
        return $this->campaign->collaborations->where('status', '!=', 'cancelled');
    }

    /** Wszystkie publikacje kampanii: treści kampanii + publikacje materiałów influencerów. */
    public function publications(): Collection
    {
        $ids = Publication::whereIn('content_id', $this->campaign->contents->pluck('id'))->pluck('id')
            ->merge($this->campaign->collaborations->flatMap->materials->pluck('publication_id')->filter())->unique();

        return Publication::with(['latestMeasurement', 'content', 'account'])->whereIn('id', $ids)->get();
    }

    public function costs(): array
    {
        $minutes = (int) TimeEntry::whereHas('ticket', fn ($q) => $q->where('campaign_id', $this->campaign->id))->sum('minutes');
        $costs = [
            'rates' => (float) $this->collabs()->sum('rate'),
            'products' => (float) $this->collabs()->flatMap->shipments->sum('value'),
            'promotion' => (float) $this->publications()->where('promoted', true)->sum('promo_budget'),
            'hours' => round($minutes / 60 * self::hourlyRate(), 2),
        ];
        $costs['minutes'] = $minutes;
        $costs['total'] = $costs['rates'] + $costs['products'] + $costs['promotion'] + $costs['hours'];

        return $costs;
    }

    public function results(): array
    {
        $pubs = $this->publications();

        return [
            'publications' => $pubs->where('status', 'published')->count(),
            'reach' => (int) $pubs->sum(fn ($p) => $p->latestMeasurement?->reach ?? 0),
            'engagement' => (int) $pubs->sum(fn ($p) => $p->engagement()),
            'clicks' => (int) $pubs->sum(fn ($p) => $p->latestMeasurement?->clicks ?? 0),
            'sales' => (int) $this->collabs()->sum('sales'),
            'revenue' => (float) $this->collabs()->sum('revenue') + (float) $this->campaign->revenue,
            'materials' => $this->collabs()->flatMap->materials->where('is_received', true)->count(),
        ];
    }

    public function summary(): array
    {
        $c = $this->costs();
        $r = $this->results();
        $influencerCost = $c['rates'] + $c['products'];

        return [
            'per_material' => $r['materials'] ? round($influencerCost / $r['materials'], 2) : null,
            'per_interaction' => $r['engagement'] ? round($c['total'] / $r['engagement'], 2) : null,
            'roas' => $c['total'] > 0 && $r['revenue'] > 0 ? round($r['revenue'] / $c['total'], 2) : null,
        ];
    }

    /** Porównanie influencerów. */
    public function byInfluencer(): Collection
    {
        return $this->collabs()->map(function ($collab) {
            $res = $collab->results();
            $cost = $collab->cost();

            return (object) [
                'collab' => $collab,
                'cost' => $cost,
                'materials' => $collab->materials->where('is_received', true)->count(),
                'reach' => $res['reach'],
                'engagement' => $res['engagement'],
                'cpe' => $res['engagement'] ? round($cost / $res['engagement'], 2) : null,
                'sales' => (int) $collab->sales,
            ];
        })->sortByDesc('engagement')->values();
    }

    /** Porównanie formatów treści. */
    public function byFormat(): Collection
    {
        return $this->publications()->where('status', 'published')->groupBy(fn ($p) => $p->content->format)
            ->map(fn ($pubs, $format) => (object) [
                'format' => $format,
                'count' => $pubs->count(),
                'reach' => (int) $pubs->sum(fn ($p) => $p->latestMeasurement?->reach ?? 0),
                'engagement' => (int) $pubs->sum(fn ($p) => $p->engagement()),
            ])->sortByDesc('engagement')->values();
    }
}
