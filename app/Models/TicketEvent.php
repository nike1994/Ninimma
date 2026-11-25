<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'user_id', 'type', 'data', 'is_internal'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_internal' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function description(): string
    {
        $d = $this->data ?? [];
        if (isset($d['from'])) {
            $d['from'] = __('status.'.$d['from']);
        }
        if (isset($d['priority'])) {
            $d['priority'] = __('priority.'.$d['priority']);
        }
        if (isset($d['to'])) {
            $d['to'] = __('status.'.$d['to']);
        }

        return __('event.'.$this->type, array_map('strval', array_filter($d, 'is_scalar')));
    }
}
