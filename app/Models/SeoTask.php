<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoTask extends Model
{
    protected $fillable = ['site_id', 'ticket_id', 'description', 'priority', 'status'];

    protected $attributes = ['status' => 'todo', 'priority' => 2];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
}
