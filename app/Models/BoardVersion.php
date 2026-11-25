<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['board_id', 'user_id', 'elements', 'description', 'is_auto', 'revision'];

    protected function casts(): array
    {
        return ['elements' => 'array', 'is_auto' => 'boolean', 'created_at' => 'datetime'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
