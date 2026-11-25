<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'company_name', 'tax_id', 'address', 'contact_email', 'contact_phone', 'retention_months', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
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
