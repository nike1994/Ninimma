<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardFile extends Model
{
    public $timestamps = false;

    protected $fillable = ['board_id', 'excalidraw_id', 'file_usage_id', 'mime'];

    public function usage(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'file_usage_id');
    }
}
