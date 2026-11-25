<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function index(Request $request)
    {
        $logs = SystemLog::query()
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.system', [
            'logs' => $logs,
            'types' => SystemLog::distinct()->orderBy('type')->pluck('type'),
            'lastCron' => Cache::get('agency:last_cron'),
            'pendingJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
        ]);
    }
}
