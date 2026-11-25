<?php

namespace App\Enums;

enum Platform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';
    case Pinterest = 'pinterest';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram', self::Facebook => 'Facebook', self::TikTok => 'TikTok',
            self::YouTube => 'YouTube', self::LinkedIn => 'LinkedIn', self::Pinterest => 'Pinterest',
        };
    }

    /** Kolor platformy (wykresy, plakietki). */
    public function color(): string
    {
        return match ($this) {
            self::Instagram => '#d6336c', self::Facebook => '#1877f2', self::TikTok => '#111827',
            self::YouTube => '#e11d2a', self::LinkedIn => '#0a66c2', self::Pinterest => '#bd081c',
        };
    }
}
