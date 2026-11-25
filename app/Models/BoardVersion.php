<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['board_id', 'user_id', 'elements', 'description', 'is_auto', 'revision', 'preview_usage_id'];

    protected function casts(): array
    {
        return ['elements' => 'array', 'is_auto' => 'boolean', 'created_at' => 'datetime'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function preview(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'preview_usage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
