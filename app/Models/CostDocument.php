<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Faktura wystawiona w zewnętrznym programie (KSeF): w panelu tylko PDF, kwota i status płatności. */
class CostDocument extends Model
{
    protected $fillable = ['client_id', 'ticket_id', 'number', 'amount', 'issued_on', 'due_on', 'is_paid', 'paid_on', 'file_usage_id'];

    protected $attributes = ['is_paid' => false];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'paid_on' => 'date', 'is_paid' => 'boolean', 'amount' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function pdf(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'file_usage_id');
    }

    public function isOverdue(): bool
    {
        return ! $this->is_paid && $this->due_on && $this->due_on->isBefore(today());
    }
}
