<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Udział grupy odbiorców kampanii (płeć, wiek, miasto, kraj) w procentach. */
class CampaignAudience extends Model
{
    public const DIMENSIONS = ['gender', 'age', 'city', 'country'];

    public const AGES = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

    protected $fillable = ['campaign_id', 'dimension', 'label', 'share'];

    protected function casts(): array
    {
        return ['share' => 'float'];
    }
}
