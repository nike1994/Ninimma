<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdResult extends Model
{
    protected $fillable = ['ad_campaign_id', 'user_id', 'measured_on', 'spend', 'impressions', 'reach', 'clicks', 'conversions', 'leads', 'revenue', 'source', 'landing_views', 'add_to_cart'];

    protected function casts(): array
    {
        return ['measured_on' => 'date', 'spend' => 'decimal:2', 'revenue' => 'decimal:2'];
    }

    /**
     * Pomiar narastający pobrany z API (jeden dziennie): kolejne pobranie tego samego dnia nadpisuje wartości.
     * $vals: spend, impressions, reach, clicks, landing_views, add_to_cart, conversions, leads, revenue.
     */
    public static function storeDaily(AdCampaign $ad, array $vals, string $source): self
    {
        $vals = array_intersect_key($vals, array_flip(['spend', 'impressions', 'reach', 'clicks', 'landing_views', 'add_to_cart', 'conversions', 'leads', 'revenue']));
        $existing = self::where('ad_campaign_id', $ad->id)->where('source', $source)->whereDate('measured_on', today())->first();
        if ($existing) {
            $existing->update($vals);

            return $existing;
        }

        return self::create($vals + ['ad_campaign_id' => $ad->id, 'measured_on' => today(), 'source' => $source]);
    }

    public function ad(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'ad_campaign_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
