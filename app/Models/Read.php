<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Read extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'readable_type', 'readable_id', 'last_seen_id', 'seen_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readable(): MorphTo
    {
        return $this->morphTo();
    }
}
