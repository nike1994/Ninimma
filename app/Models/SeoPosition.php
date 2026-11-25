<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoPosition extends Model
{
    protected $fillable = ['seo_phrase_id', 'measured_on', 'position'];

    protected function casts(): array
    {
        return ['measured_on' => 'date'];
    }
}
