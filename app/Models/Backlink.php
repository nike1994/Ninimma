<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backlink extends Model
{
    protected $fillable = ['site_id', 'url', 'placed_on', 'cost', 'is_active', 'checked_on'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['placed_on' => 'date', 'checked_on' => 'date', 'is_active' => 'boolean', 'cost' => 'decimal:2'];
    }
}
