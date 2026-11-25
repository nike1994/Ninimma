<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\SiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Klient: raport miesięczny opieki nad stroną (bez notatek roboczych i kosztów linków). */
class ClientSiteController extends Controller
{
    public function __invoke(Request $request, SiteService $sites)
    {
        $user = $request->user();
        $all = Site::where('client_id', $user->client_id)->where('is_active', true)->orderBy('domain')->get();
        $site = $request->integer('site') ? $all->firstWhere('id', $request->integer('site')) : $all->first();
        $month = Carbon::parse($request->query('month', 'now'))->startOfMonth();

        return view('sites.client', [
            'sites' => $all, 'site' => $site, 'month' => $month,
            'report' => $site ? $sites->monthlyReport($site, $month) : null,
        ]);
    }
}
