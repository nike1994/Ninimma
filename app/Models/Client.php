<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'company_name', 'tax_id', 'address', 'contact_email', 'contact_phone', 'retention_months', 'is_active', 'arrangements',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'social_defaults' => 'array'];
    }

    /**
     * Ustawienia domyślne planowania social media:
     * accounts: [{id, time}], hashtags, hashtag_sets: [{name, tags}], visible_to_client, auto_publish, type, format.
     */
    public function socialDefaults(): array
    {
        return ($this->social_defaults ?? []) + ['accounts' => [], 'hashtags' => '', 'hashtag_sets' => [],
            'visible_to_client' => false, 'auto_publish' => true, 'type' => 'post', 'format' => 'image'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class)->orderBy('label');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
