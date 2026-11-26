<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUSES = ['draft', 'review', 'active', 'finished'];

    /** Rodzaj celu kampanii — od niego zależą wskaźniki, lejek i wartość docelowa. */
    public const OBJECTIVES = ['sales', 'leads', 'traffic', 'awareness', 'engagement'];

    protected $fillable = ['client_id', 'name', 'goal', 'objective', 'target_value', 'budget', 'starts_on', 'ends_on', 'status', 'revenue', 'leads'];

    protected $attributes = ['status' => 'draft', 'budget' => 0];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'budget' => 'decimal:2', 'revenue' => 'decimal:2', 'target_value' => 'float'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class);
    }

    /** Cel kampanii: zapisany albo wywnioskowany z danych (przychód → sprzedaż, leady → leady, inaczej zasięg). */
    public function objectiveKey(): string
    {
        if (in_array($this->objective, self::OBJECTIVES, true)) {
            return $this->objective;
        }
        if ((float) $this->revenue > 0) {
            return 'sales';
        }

        return $this->leads ? 'leads' : 'awareness';
    }

    /**
     * Zapisuje strukturę odbiorców: wiersze [wymiar, etykieta, udział %]. Podane wymiary są zastępowane w całości
     * (np. import samego wieku i płci nie kasuje miast).
     */
    public function saveAudience(array $rows, ?array $onlyDims = null, string $source = 'manual'): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $onlyDims, $source) {
            $this->audiences()->when($onlyDims, fn ($q) => $q->whereIn('dimension', $onlyDims))->delete();
            foreach ($rows as [$dim, $label, $share]) {
                $this->audiences()->updateOrCreate(['dimension' => $dim, 'label' => mb_substr($label, 0, 80)], ['share' => round(min(100, $share), 2), 'source' => $source]);
            }
        });
    }

    public function utmLinks(): HasMany
    {
        return $this->hasMany(UtmLink::class)->latest();
    }

    /** Wydatki kampanii (bez godzin pracy): stawki, produkty i reklamy — do paska wykorzystania budżetu. */
    public function spent(): float
    {
        $collabs = $this->collaborations()->where('status', '!=', 'cancelled')->with('shipments')->get();

        return (float) $collabs->sum('rate') + (float) $collabs->flatMap->shipments->sum('value')
            + (float) $this->ads()->with('latestResult')->get()->sum(fn ($a) => $a->costForReports());
    }

    public function budgetUsedPct(): ?int
    {
        return (float) $this->budget > 0 ? (int) round($this->spent() / (float) $this->budget * 100) : null;
    }

    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    /** Suma zasięgu z ostatnich pomiarów publikacji przypiętych treści. */
    public function reach(): int
    {
        return (int) Publication::with('latestMeasurement')->whereIn('content_id', $this->contents()->select('id'))
            ->get()->sum(fn ($p) => $p->latestMeasurement?->reach ?? 0);
    }

    /** Główny wynik wg celu kampanii (pulpit): etykieta, wartość i realizacja celu w %. */
    public function headlineMetric(): array
    {
        $ins = new \App\Services\CampaignInsights($this);
        $g = $ins->goal($ins->totals());
        $value = $g->format === 'money'
            ? ($g->value >= 100000 ? \App\Support\Num::short((int) $g->value) : number_format((float) $g->value, 0, ',', ' ')).' zł'
            : \App\Support\Num::short((int) $g->value);

        return ['label' => __('mkt.m_'.$g->metric), 'value' => $value, 'pct' => $g->pct];
    }
}
