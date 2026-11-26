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

        $inline = in_array($file->mime, self::INLINE, true) && ! $request->boolean('download');

        $headers = $file->mime === 'image/svg+xml' ? ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"] : [];

        return response()->file($store->absolutePath($file), $headers + [
            'Content-Type' => $file->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename*=UTF-8\'\''.rawurlencode($usage->name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
