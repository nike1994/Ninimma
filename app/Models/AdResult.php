<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdResult extends Model
{
    protected $fillable = ['ad_campaign_id', 'user_id', 'measured_on', 'spend', 'impressions', 'reach', 'clicks', 'conversions', 'leads', 'revenue', 'source'];

    protected function casts(): array
    {
        return ['measured_on' => 'date', 'spend' => 'decimal:2', 'revenue' => 'decimal:2'];
    }

    public function ad(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'ad_campaign_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
