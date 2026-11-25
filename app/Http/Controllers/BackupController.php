<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Homelab pobiera najnowszą kopię bazy: GET /backup/latest z nagłówkiem "Authorization: Bearer <AGENCY_BACKUP_TOKEN>". */
class BackupController extends Controller
{
    private function guard(Request $request): void
    {
        $token = (string) config('agency.backup_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 404);
    }

    /** Lista paczek eksportu (w tym offline) — homelab musi mieć ich własną kopię. */
    public function exports(Request $request)
    {
        $this->guard($request);

        return response()->json(\App\Models\ExportPackage::latest()->get(['name', 'kind', 'size', 'created_at']));
    }

    public function export(Request $request, string $name)
    {
        $this->guard($request);
        $pkg = \App\Models\ExportPackage::where('name', $name)->firstOrFail();

        return Storage::disk('local')->download($pkg->path(), $pkg->name);
    }

    public function latest(Request $request)
    {
        $this->guard($request);

        $files = collect(Storage::disk('local')->files('backups'))->sort()->values();
        abort_if($files->isEmpty(), 404);

        return Storage::disk('local')->download($files->last());
    }
}
