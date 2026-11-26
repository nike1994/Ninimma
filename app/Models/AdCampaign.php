<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Reklama lub promowany post (Meta, Google, TikTok…) — z budżetem planowanym i wynikami płatnymi. */
class AdCampaign extends Model
{
    public const PLATFORMS = ['meta', 'google', 'tiktok', 'linkedin', 'pinterest', 'youtube', 'other'];

    public const OBJECTIVES = ['reach', 'traffic', 'engagement', 'leads', 'conversions', 'video_views'];

    public const STATUSES = ['planned', 'active', 'paused', 'finished'];

    protected $fillable = ['client_id', 'campaign_id', 'publication_id', 'material_id', 'platform', 'name', 'external_name', 'objective',
        'budget', 'starts_on', 'ends_on', 'status', 'target_url', 'notes'];

    protected $attributes = ['status' => 'planned', 'objective' => 'traffic', 'budget' => 0];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'budget' => 'decimal:2'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function publication(): BelongsTo { return $this->belongsTo(Publication::class); }
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function results(): HasMany { return $this->hasMany(AdResult::class)->orderBy('measured_on')->orderBy('id'); }
    public function latestResult(): HasOne { return $this->hasOne(AdResult::class)->latestOfMany('measured_on'); }

    /** Wynik narastający (ostatni pomiar). */
    public function totals(): array
    {
        $r = $this->relationLoaded('latestResult') ? $this->latestResult : $this->latestResult()->first();

        return [
            'spend' => (float) ($r->spend ?? 0), 'impressions' => (int) ($r->impressions ?? 0), 'reach' => (int) ($r->reach ?? 0),
            'clicks' => (int) ($r->clicks ?? 0), 'conversions' => (int) ($r->conversions ?? 0), 'leads' => (int) ($r->leads ?? 0),
            'revenue' => (float) ($r->revenue ?? 0), 'measured_on' => $r?->measured_on,
            'landing_views' => (int) ($r->landing_views ?? 0), 'add_to_cart' => (int) ($r->add_to_cart ?? 0),
        ];
    }

    /** Wydatek do kosztów: rzeczywisty, a bez pomiarów — planowany budżet. */
    public function costForReports(): float
    {
        $t = $this->totals();

        return $t['measured_on'] ? $t['spend'] : (float) $this->budget;
    }

    public function budgetUsedPct(): ?int
    {
        return (float) $this->budget > 0 ? (int) round($this->totals()['spend'] / (float) $this->budget * 100) : null;
    }

    public function endsSoon(int $days = 2): bool
    {
        return $this->status === 'active' && $this->ends_on && $this->ends_on->between(today(), today()->addDays($days));
    }

    public function scopeVisibleToClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
}
