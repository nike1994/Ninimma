<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseExtension extends Model
{
    protected $fillable = ['material_id', 'user_id', 'previous_until', 'new_until', 'channels', 'cost', 'note'];

    protected function casts(): array
    {
        return ['previous_until' => 'date', 'new_until' => 'date', 'channels' => 'array', 'cost' => 'decimal:2'];
    }

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
