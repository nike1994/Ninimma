<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Homelab pobiera najnowszą kopię bazy: GET /backup/latest z nagłówkiem "Authorization: Bearer <AGENCY_BACKUP_TOKEN>". */
class BackupController extends Controller
{
    public function latest(Request $request)
    {
        $token = (string) config('agency.backup_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 404);

        $files = collect(Storage::disk('local')->files('backups'))->sort()->values();
        abort_if($files->isEmpty(), 404);

        return Storage::disk('local')->download($files->last());
    }
}
