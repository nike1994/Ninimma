<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const CREATED_AT = null;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        // Pamięć na czas jednego żądania (ustawienia czytane wiele razy w widokach).
        return \Illuminate\Support\Facades\Cache::store('array')->rememberForever('setting:'.$key, fn () => static::find($key)?->value) ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        \Illuminate\Support\Facades\Cache::store('array')->forget('setting:'.$key);
    }

    public static function termsVersion(): int
    {
        return (int) static::get('terms_version', 0);
    }
}
