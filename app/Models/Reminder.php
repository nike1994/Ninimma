<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Przypomnienie z datą przypięte do dowolnego rekordu (osoba, współpraca, zlecenie, materiał, strona). */
class Reminder extends Model
{
    protected $fillable = ['remindable_type', 'remindable_id', 'user_id', 'due_on', 'body', 'is_done'];

    protected $attributes = ['is_done' => false];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'is_done' => 'boolean'];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): ?string
    {
        return match (true) {
            $this->remindable instanceof Influencer => route('influencers.show', $this->remindable),
            $this->remindable instanceof Collaboration => route('collaborations.show', $this->remindable),
            $this->remindable instanceof Ticket => route('tickets.show', $this->remindable),
            default => null,
        };
    }
}
