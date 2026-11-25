<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Services\SocialStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SocialDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $period = in_array($request->query('period'), SocialStats::PERIODS, true) ? $request->query('period') : '30d';
        $clientId = $request->integer('client') ?: null;
        $stats = new SocialStats($period, $clientId);

        $accounts = SocialAccount::with('client')->where('is_active', true)->orderBy('name')->get();
        $brands = $accounts->groupBy('client_id')->map(fn ($accs) => (object) ['client' => $accs->first()->client, 'accounts' => $accs])
            ->sortBy(fn ($b) => $b->client->name)->values();
        $weekStart = CarbonImmutable::parse($request->query('week', 'now'))->startOfWeek();
        $rows = $stats->byBrand($clientId ? $accounts->where('client_id', $clientId) : $accounts);

        return view('social.dashboard', [
            'stats' => $stats,
            'period' => $period,
            'clientId' => $clientId,
            'brands' => $brands,
            'accountsCount' => $accounts->count(),
            'kpis' => $stats->kpis(),
            'funnel' => $stats->funnel(),
            'weekStart' => $weekStart,
            'week' => $stats->week($weekStart),
            'rows' => $rows,
            'trend' => $stats->engagementTrend($rows),
            'platforms' => $stats->platformShares(),
            'totalReach' => $stats->totalReach(),
            'statsQueue' => SocialStats::statsQueue($clientId)->take(5),
            'preparing' => Content::with('client')->whereIn('stage', ['idea', 'preparing'])
                ->when($clientId, fn ($q) => $q->where('client_id', $clientId))->latest('updated_at')->limit(5)->get(),
            'awaitingClient' => Publication::with(['content', 'account'])->where('visible_to_client', true)
                ->when($clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $clientId)))
                ->where('status', 'planned')->where(fn ($q) => $q->whereNull('client_status')->orWhere('client_status', 'changes'))
                ->orderBy('scheduled_at')->limit(5)->get(),
        ]);
    }
}
