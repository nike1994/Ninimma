<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UtmLink extends Model
{
    protected $fillable = ['campaign_id', 'base_url', 'source', 'medium', 'name', 'content', 'term', 'full_url'];

    /** Składa adres z parametrami UTM (zachowuje istniejące parametry i kotwicę). */
    public static function build(string $base, array $utm): string
    {
        [$url, $fragment] = array_pad(explode('#', $base, 2), 2, null);
        $params = array_filter([
            'utm_source' => $utm['source'] ?? null, 'utm_medium' => $utm['medium'] ?? null, 'utm_campaign' => $utm['name'] ?? null,
            'utm_content' => $utm['content'] ?? null, 'utm_term' => $utm['term'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $params = array_map(fn ($v) => trim(mb_strtolower(preg_replace('/\s+/', '_', $v))), $params);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params).($fragment !== null ? '#'.$fragment : '');
    }
}
