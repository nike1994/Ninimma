<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Współpraca osoby przy kampanii; etapy: umowa → brief → przesyłka → materiały → publikacja → rozliczenie. */
class Collaboration extends Model
{
    public const STAGES = ['contract', 'brief', 'shipment', 'materials', 'publication', 'settlement'];

    protected $fillable = ['influencer_id', 'campaign_id', 'rate', 'billing', 'scope', 'license_scope', 'license_from', 'license_to',
        'private_notes', 'needs_shipment', 'tracking_link', 'discount_code', 'sales', 'revenue', 'license_channels', 'requires_takedown'];

    protected $attributes = ['status' => 'active', 'billing' => 'contract', 'needs_shipment' => true, 'rate' => 0];

    protected function casts(): array
    {
        $c = ['license_channels' => 'array', 'requires_takedown' => 'boolean', 'license_from' => 'date', 'license_to' => 'date', 'needs_shipment' => 'boolean', 'rate' => 'decimal:2', 'revenue' => 'decimal:2'];
        foreach (['contract_sent', 'contract_signed', 'brief_sent', 'brief_accepted', 'materials_complete', 'published', 'paid'] as $col) {
            $c[$col.'_at'] = 'datetime';
        }

        return $c;
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'contract_usage_id');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function shipmentsDelivered(): bool
    {
        $s = $this->relationLoaded('shipments') ? $this->shipments : $this->shipments()->get();

        return $s->isNotEmpty() && $s->every(fn ($x) => $x->delivered_on !== null);
    }

    /** Bieżący etap (lub 'done' / 'cancelled'). */
    public function stage(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        return match (true) {
            ! $this->contract_signed_at => 'contract',
            ! $this->brief_accepted_at => 'brief',
            $this->needs_shipment && ! $this->shipmentsDelivered() => 'shipment',
            ! $this->materials_complete_at => 'materials',
            ! $this->published_at => 'publication',
            ! $this->paid_at => 'settlement',
            default => 'done',
        };
    }

    public function stageIndex(): int
    {
        $i = array_search($this->stage(), self::STAGES, true);

        return $i === false ? count(self::STAGES) : $i;
    }

    /** Koszt: stawka + wartość wysłanych produktów. */
    public function cost(): float
    {
        return (float) $this->rate + (float) $this->shipments->sum('value');
    }

    /** Zasięg i zaangażowanie z publikacji powiązanych z materiałami. */
    public function results(): array
    {
        $pubs = Publication::with('latestMeasurement')->whereIn('id', $this->materials->pluck('publication_id')->filter())->get();

        return [
            'reach' => (int) $pubs->sum(fn ($p) => $p->latestMeasurement?->reach ?? 0),
            'engagement' => (int) $pubs->sum(fn ($p) => $p->engagement()),
            'clicks' => (int) $pubs->sum(fn ($p) => $p->latestMeasurement?->clicks ?? 0),
        ];
    }
}
