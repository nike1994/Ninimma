<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Osoba (bez konta) — rekord w panelu administratora. Klient nigdy nie widzi influencerów. */
class Influencer extends Model
{
    protected $fillable = ['name', 'photo_usage_id', 'profiles', 'portfolio_url', 'email', 'phone', 'address', 'parcel_locker', 'tags', 'rating', 'notes', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['profiles' => 'array', 'tags' => 'array', 'is_active' => 'boolean'];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'photo_usage_id');
    }

    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function followers(): int
    {
        return (int) collect($this->profiles ?? [])->sum(fn ($p) => (int) ($p['followers'] ?? 0));
    }
}
