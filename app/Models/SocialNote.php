<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialNote extends Model
{
    protected $fillable = ['user_id', 'body', 'is_done'];

    protected $attributes = ['is_done' => false];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }
}
