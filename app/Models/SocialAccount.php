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

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['platform' => Platform::class, 'is_active' => 'boolean'];
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
