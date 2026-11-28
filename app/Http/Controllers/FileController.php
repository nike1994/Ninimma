<?php

namespace App\Http\Controllers;

use App\Models\FileUsage;
use App\Services\FileStore;
use Illuminate\Http\Request;

class FileController extends Controller
{
    private const INLINE = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime', 'application/pdf'];

    public function index(Request $request)
    {
        return view('files.index', [
            'files' => DashboardController::visibleFiles($request->user())->latest('id')->paginate(40),
        ]);
    }

    public function show(Request $request, FileUsage $usage, FileStore $store)
    {
        $this->authorize('view', $usage);
        $file = $usage->file;
        abort_if($file->location !== 'hosting', 410, __('files.offline', ['package' => $file->offline_package]));

        $user = $request->user();
        if (\App\Services\MediaProtection::isLockedFor($user, $usage)) {
            \App\Services\MediaProtection::log($usage, $user, 'blocked');
            abort(403, __('files.license_locked'));
        }
        if (\App\Services\MediaProtection::isProtectedFor($user, $usage)) {
            return $this->protectedResponse($request, $usage, $store);
        }
        if ($request->boolean('download') && ! $user->isAdmin() && $usage->usable instanceof \App\Models\Material) {
            \App\Services\MediaProtection::log($usage, $user, 'download');
        }

        $inline = in_array($file->mime, self::INLINE, true) && ! $request->boolean('download');

        $headers = $file->mime === 'image/svg+xml' ? ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"] : [];

        return response()->file($store->absolutePath($file), $headers + [
            'Content-Type' => $file->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename*=UTF-8\'\''.rawurlencode($usage->name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ])->setPrivate();
    }

    /**
     * Tryb chroniony (klient): bez pobierania. Zdjęcie — kopia ze znakiem wodnym. Film — tylko dla odtwarzacza
     * w panelu (przeglądarka oznacza takie żądanie jako „video”); otwarcie linku wprost albo pobranie jest blokowane.
     */
    private function protectedResponse(Request $request, FileUsage $usage, FileStore $store)
    {
        $user = $request->user();
        $file = $usage->file;
        $dest = $request->header('Sec-Fetch-Dest');
        $noStore = ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline', 'X-Robots-Tag' => 'noindex'];

        if ($request->boolean('download')) {
            \App\Services\MediaProtection::log($usage, $user, 'blocked');
            abort(403, __('files.download_blocked'));
        }
        if ($file->isImage() && $file->mime !== 'image/svg+xml') {
            $path = app(\App\Services\MediaProtection::class)->watermarkedImage($usage, $user, $store->absolutePath($file));

            return response()->file($path, $noStore + ['Content-Type' => 'image/jpeg'])->setPrivate();
        }
        if ($file->isVideo() && $dest === 'video') {
            return response()->file($store->absolutePath($file), $noStore + ['Content-Type' => $file->mime])->setPrivate();
        }
        \App\Services\MediaProtection::log($usage, $user, 'blocked');
        abort(403, __('files.download_blocked'));
    }

    /** Kadr filmu: oryginał dla zespołu, ze znakiem wodnym dla klienta w trybie chronionym. */
    public function poster(Request $request, FileUsage $usage, FileStore $store)
    {
        $this->authorize('view', $usage);
        $user = $request->user();
        $poster = $usage->file->poster;
        abort_unless($poster && $poster->location === 'hosting', 404);
        abort_if(\App\Services\MediaProtection::isLockedFor($user, $usage), 403, __('files.license_locked'));
        if (\App\Services\MediaProtection::isProtectedFor($user, $usage)) {
            $path = app(\App\Services\MediaProtection::class)->watermarkedImage($usage, $user, $store->absolutePath($poster), 'p');

            return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
        }

        return response()->file($store->absolutePath($poster), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    /**
     * Zapis kadru zrobionego w przeglądarce (przy wgrywaniu albo przy pierwszym podglądzie przez zespół).
     * Obraz jest dekodowany i zapisywany na nowo jako JPEG — nic poza pikselami nie trafia na serwer.
     */
    public function storePoster(Request $request, FileUsage $usage, FileStore $store)
    {
        $user = $request->user();
        $file = $usage->file;
        abort_unless($file->isVideo() && ($user->isAdmin() || $usage->uploaded_by === $user->id), 403);
        if ($file->poster_id && ! $user->isAdmin()) {
            return response()->json(['ok' => true]);
        }
        $request->validate(['poster' => ['required', 'file', 'max:3072', 'mimetypes:image/jpeg,image/png,image/webp']]);
        $src = @imagecreatefromstring((string) file_get_contents($request->file('poster')->getRealPath()));
        abort_unless($src, 422);
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 960 / max($w, $h));
        $im = imagecreatetruecolor(max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale)));
        imagecopyresampled($im, $src, 0, 0, 0, 0, imagesx($im), imagesy($im), $w, $h);
        $tmp = tempnam(sys_get_temp_dir(), 'poster');
        imagejpeg($im, $tmp, 80);
        imagedestroy($src);
        imagedestroy($im);
        $poster = $store->storeLocalFile($tmp, 'image/jpeg');
        @unlink($tmp);
        $file->forceFill(['poster_id' => $poster->id])->save();

        return response()->json(['ok' => true, 'url' => route('files.poster', $usage)]);
    }
}
