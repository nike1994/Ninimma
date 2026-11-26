<?php

namespace App\Http\Controllers;

use App\Models\FileUsage;
use App\Services\FileStore;

/** Plik treści dla platformy (podpisany, krótko ważny link) — tylko pliki postów, nigdy materiały ani załączniki zleceń. */
class PublicMediaController extends Controller
{
    public function __invoke(FileUsage $usage, FileStore $store)
    {
        abort_unless($usage->usable_type === 'content' && $usage->file && $usage->file->location === 'hosting', 404);

        return response()->file($store->absolutePath($usage->file), [
            'Content-Type' => $usage->file->mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
