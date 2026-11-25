<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'level', 'message', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(string $type, string $message, array $details = [], string $level = 'info'): self
    {
        return static::create(compact('type', 'message', 'details', 'level'));
    }
}
