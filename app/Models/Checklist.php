<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Szablon listy kontrolnej przeglądu dla typu strony. */
class Checklist extends Model
{
    protected $fillable = ['name', 'platform', 'items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
