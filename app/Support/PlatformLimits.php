<?php

namespace App\Support;

use App\Enums\Platform;

/**
 * Limity tekstu posta na platformach (znaki, hashtagi) i miejsce ucięcia „…więcej” w podglądzie.
 * Znaki liczone jak na platformach — po znakach Unicode (emoji = 1–2), nie po bajtach.
 */
class PlatformLimits
{
    public const LIMITS = [
        'instagram' => ['chars' => 2200, 'tags' => 30, 'more' => 125],
        'facebook' => ['chars' => 63206, 'tags' => null, 'more' => 480],
        'tiktok' => ['chars' => 2200, 'tags' => null, 'more' => 100],
        'linkedin' => ['chars' => 3000, 'tags' => null, 'more' => 210],
        'pinterest' => ['chars' => 500, 'tags' => 20, 'more' => 50],
        'youtube' => ['chars' => 5000, 'tags' => 15, 'more' => 100],
    ];

    public static function count(string $text): array
    {
        preg_match_all('/(?<![\p{L}\p{N}_])#[\p{L}\p{N}_]+/u', $text, $m);

        return ['chars' => mb_strlen($text), 'tags' => count($m[0])];
    }

    /** Komunikaty o przekroczonych limitach (puste = w porządku). */
    public static function check(Platform $platform, string $text): array
    {
        $l = self::LIMITS[$platform->value] ?? null;
        if (! $l) {
            return [];
        }
        $c = self::count($text);
        $out = [];
        if ($c['chars'] > $l['chars']) {
            $out[] = __('social.limit_chars', ['platform' => $platform->label(), 'n' => $c['chars'], 'max' => $l['chars']]);
        }
        if ($l['tags'] && $c['tags'] > $l['tags']) {
            $out[] = __('social.limit_tags', ['platform' => $platform->label(), 'n' => $c['tags'], 'max' => $l['tags']]);
        }

        return $out;
    }

    /** Tekst, który pójdzie na platformę: opis pod platformę albo opis treści + hashtagi. */
    public static function textFor(?string $caption, ?string $description, ?string $hashtags): string
    {
        return filled($caption) ? (string) $caption : trim(trim((string) $description)."\n\n".trim((string) $hashtags));
    }
}
