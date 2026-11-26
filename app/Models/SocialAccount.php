<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'platform', 'name', 'handle', 'url', 'is_active'];

    /** Tokeny nigdy nie trafiają do widoków ani JSON-a. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['platform' => Platform::class, 'is_active' => 'boolean', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime', 'connected_at' => 'datetime', 'api_meta' => 'array'];
    }

    /** Konto połączone z API platformy (i integracja włączona). */
    public function isConnected(): bool
    {
        return $this->connected_at !== null && $this->external_id && $this->access_token
            && \App\Services\Social\Integrations::enabledFor($this->platform);
    }

    public function disconnect(?string $error = null): void
    {
        $this->forceFill(['external_id' => null, 'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null,
            'connected_at' => null, 'api_meta' => null, 'api_error' => $error])->save();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
