<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Measurement extends Model
{
    protected $fillable = ['publication_id', 'user_id', 'measured_on', 'reach', 'reactions', 'comments', 'shares', 'clicks', 'views', 'source'];

    protected function casts(): array
    {
        return ['measured_on' => 'date'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
