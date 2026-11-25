<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = ['email', 'name', 'role', 'client_id', 'token_hash', 'locale', 'invited_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Tworzy zaproszenie i zwraca [zaproszenie, jawny token do linku]. */
    public static function issue(array $attributes): array
    {
        $token = Str::random(48);
        $invitation = static::create($attributes + [
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(config('agency.invitation_valid_days')),
        ]);

        return [$invitation, $token];
    }

    public static function findValid(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
