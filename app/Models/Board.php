<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Tablica Excalidraw należąca do zlecenia. Elementy przechowywane jako mapa id → element. */
class Board extends Model
{
    protected $fillable = ['ticket_id', 'name', 'updated_by'];

    protected $attributes = ['revision' => 0];

    protected function casts(): array
    {
        return ['elements' => 'array', 'revisions' => 'array', 'app_state' => 'array'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BoardVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(BoardVersion::class)->latestOfMany();
    }

    public function files(): HasMany
    {
        return $this->hasMany(BoardFile::class);
    }

    public function reads(): MorphMany
    {
        return $this->morphMany(Read::class, 'readable');
    }

    /** Czy jest wersja nowsza niż ostatnio przejrzana przez użytkownika (kropka). */
    public function hasUnseenVersionFor(User $user): bool
    {
        $latest = $this->relationLoaded('latestVersion') ? $this->latestVersion : $this->latestVersion()->first();
        if (! $latest || $latest->user_id === $user->id) {
            return false;
        }
        $read = $this->reads()->where('user_id', $user->id)->first();

        return ! $read || $read->last_seen_id < $latest->id;
    }

    public function liveElements(): array
    {
        return array_values(array_filter($this->elements ?? [], fn ($e) => empty($e['isDeleted'])));
    }
}
