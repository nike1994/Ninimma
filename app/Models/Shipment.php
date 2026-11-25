<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = ['collaboration_id', 'contents', 'quantity', 'value', 'tracking_number', 'sent_on', 'delivered_on'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'delivered_on' => 'date', 'value' => 'decimal:2'];
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }
}
