<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Wycena jako limit pracy (godziny lub kwota), z opcjami i decyzją klienta. */
class Quote extends Model
{
    protected $fillable = ['ticket_id', 'version', 'hours_limit', 'amount', 'justification', 'options', 'is_diagnosis', 'created_by'];

    protected $attributes = ['status' => 'pending', 'is_diagnosis' => false];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_diagnosis' => 'boolean', 'decided_at' => 'datetime', 'hours_limit' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Zaakceptowany limit w minutach (z wybranej opcji albo z wyceny). */
    public function acceptedMinutes(): ?int
    {
        if ($this->status !== 'accepted') {
            return null;
        }
        $opt = $this->chosen_option !== null ? ($this->options[$this->chosen_option] ?? null) : null;
        $hours = $opt['hours'] ?? $this->hours_limit;

        return $hours !== null && $hours !== '' ? (int) round((float) $hours * 60) : null;
    }

    public function acceptedAmount(): ?float
    {
        if ($this->status !== 'accepted') {
            return null;
        }
        $opt = $this->chosen_option !== null ? ($this->options[$this->chosen_option] ?? null) : null;
        $amount = $opt['amount'] ?? $this->amount;

        return $amount !== null && $amount !== '' ? (float) $amount : null;
    }
}
