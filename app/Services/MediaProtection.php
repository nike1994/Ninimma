<?php

namespace App\Services;

use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ochrona filmów i zdjęć pokazywanych klientowi:
 * zamiast oryginału — podgląd ze znakiem wodnym (zdjęcia) albo odtwarzanie tylko w odtwarzaczu panelu (filmy),
 * bez pobierania; każda próba pobrania trafia do dziennika.
 */
class MediaProtection
{
    public const PREVIEW_MAX = 1280;

    /** Czy klient ogląda ten plik tylko w trybie chronionym. */
    public static function isProtectedFor(User $user, FileUsage $usage): bool
    {
        if ($user->isAdmin()) {
            return false;
        }
        $usable = $usage->usable;
        if ($usable instanceof Material) {
            // Oryginał dopiero po zgodzie agencji (np. po wykupieniu licencji przez klienta).
            return ! $usable->client_download;
        }
        if ($usable instanceof Content) {
            // Projekty postów chronione do publikacji — potem i tak są publiczne.
            return $usable->publications()->where('status', 'published')->doesntExist();
        }

        return false;
    }

    /** Materiał po wygaśnięciu licencji klient widzi tylko jako opis (bez podglądu). */
    public static function isLockedFor(User $user, FileUsage $usage): bool
    {
        return ! $user->isAdmin() && $usage->usable instanceof Material && $usage->usable->licenseExpired();
    }

    public static function log(FileUsage $usage, ?User $user, string $action): void
    {
        $req = request();
        DB::table('file_access_logs')->insert(['file_usage_id' => $usage->id, 'user_id' => $user?->id, 'action' => $action,
            'ip' => $req->ip(), 'user_agent' => Str::limit((string) $req->userAgent(), 250, ''), 'created_at' => now()]);
    }

    /** Zdjęcie dla klienta: pomniejszone, ze znakiem wodnym z nazwą klienta i datą (kopia w pamięci podręcznej). */
    public function watermarkedImage(FileUsage $usage, User $user, string $sourcePath, string $variant = ''): string
    {
        $label = mb_strtoupper(__('files.preview_mark')).' · '.($user->client?->name ?? $user->name).' · '.today()->format('d.m.Y');
        $key = 'previews/'.$usage->id.$variant.'-'.substr(sha1($label.$sourcePath.self::PREVIEW_MAX), 0, 16).'.jpg';
        $disk = Storage::disk('local');
        if ($disk->exists($key)) {
            return $disk->path($key);
        }
        $disk->makeDirectory('previews');
        file_put_contents($disk->path($key), self::watermark((string) file_get_contents($sourcePath), $label, self::PREVIEW_MAX) ?? abort(415));

        return $disk->path($key);
    }

    /**
     * Kopia do pokazania na zewnątrz: pomniejszona (max $max px), JPEG, ukośne powtarzane napisy na całym kadrze,
     * opcjonalnie podpis w rogu (np. czas klatki). Zwraca dane JPEG albo null, gdy to nie obraz.
     */
    public static function watermark(string $binary, string $label, int $max = self::PREVIEW_MAX, ?string $corner = null): ?string
    {
        $src = @imagecreatefromstring($binary);
        if (! $src) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $max / max($w, $h));
        [$nw, $nh] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];
        $im = imagecreatetruecolor($nw, $nh);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagecopyresampled($im, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        // Ukośne, powtarzane napisy na całym kadrze — trudne do wycięcia.
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $size = max(11, (int) round(min($nw, $nh) / 26));
        $light = imagecolorallocatealpha($im, 255, 255, 255, 78);
        $dark = imagecolorallocatealpha($im, 11, 42, 74, 92);
        $step = (int) ($size * 7);
        $ttf = function_exists('imagettftext') && is_file($font);
        for ($y = -$nh; $y < $nh * 2; $y += $step) {
            for ($x = -$nw; $x < $nw * 2; $x += (int) ($size * mb_strlen($label) * .75)) {
                if ($ttf) {
                    imagettftext($im, $size, 28, $x + 1, $y + 1, $dark, $font, $label);
                    imagettftext($im, $size, 28, $x, $y, $light, $font, $label);
                } else {
                    imagestring($im, 5, $x, $y, Str::ascii($label), $light);
                }
            }
        }
        if ($corner !== null && $corner !== '') {
            $cs = max(11, (int) round($size * .9));
            $pad = (int) round($cs * .6);
            $boxW = $ttf ? (int) (abs(imagettfbbox($cs, 0, $font, $corner)[2]) + $pad * 2) : strlen($corner) * 9 + $pad * 2;
            $boxH = $cs + $pad * 2;
            imagefilledrectangle($im, $pad, $nh - $boxH - $pad, $pad + $boxW, $nh - $pad, imagecolorallocatealpha($im, 11, 42, 74, 30));
            $ttf ? imagettftext($im, $cs, 0, $pad * 2, $nh - $pad * 2, imagecolorallocate($im, 255, 255, 255), $font, $corner)
                : imagestring($im, 5, $pad * 2, $nh - $boxH, Str::ascii($corner), imagecolorallocate($im, 255, 255, 255));
        }
        ob_start();
        imagejpeg($im, null, 82);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}
