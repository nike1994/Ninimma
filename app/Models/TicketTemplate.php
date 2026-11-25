<?php

namespace App\Models;

use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplate extends Model
{
    protected $fillable = ['client_id', 'name', 'title', 'type', 'body', 'recurrence', 'next_run_on', 'is_active'];

    protected function casts(): array
    {
        return ['next_run_on' => 'date', 'is_active' => 'boolean', 'type' => TicketType::class];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function advance(): void
    {
        $this->next_run_on = match ($this->recurrence) {
            'weekly' => $this->next_run_on->copy()->addWeek(),
            'monthly' => $this->next_run_on->copy()->addMonthNoOverflow(),
            default => null,
        };
        $this->save();
    }
}
