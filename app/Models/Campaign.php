<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUSES = ['draft', 'review', 'active', 'finished'];

    protected $fillable = ['client_id', 'name', 'goal', 'budget', 'starts_on', 'ends_on', 'status', 'revenue', 'leads'];

    protected $attributes = ['status' => 'draft', 'budget' => 0];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'budget' => 'decimal:2', 'revenue' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    /** Główny wynik: ROAS (gdy jest przychód), leady albo zasięg. */
    public function headlineMetric(): array
    {
        if ($this->revenue !== null && (float) $this->budget > 0) {
            return ['label' => 'ROAS', 'value' => number_format((float) $this->revenue / (float) $this->budget, 1, ',', ' ')];
        }
        if ($this->leads !== null) {
            return ['label' => __('social.leads'), 'value' => number_format($this->leads, 0, ',', ' ')];
        }

        return ['label' => __('social.kpi_reach'), 'value' => \App\Support\Num::short($this->reach())];
    }
}
