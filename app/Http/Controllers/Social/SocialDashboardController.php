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
            'campaigns' => \App\Models\Campaign::with('client')->where('status', '!=', 'finished')
                ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->orderByRaw("case status when 'active' then 0 when 'review' then 1 else 2 end")->limit(3)->get(),
            'tasks' => \App\Models\Ticket::with(['client', 'assignee'])->where('type', 'social')
                ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->where(fn ($q) => $q->where('status', '!=', 'done')->orWhere('closed_at', '>=', now()->subDays(2)))
                ->orderByRaw("case when status = 'done' then 0 else 1 end")->orderByRaw('due_on is null')->orderBy('due_on')->limit(6)->get(),
            'notes' => \App\Models\SocialNote::orderBy('is_done')->latest()->limit(6)->get(),
            'team' => \App\Models\User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get(),
            'clients' => \App\Models\Client::where('is_active', true)->orderBy('name')->get(),
            'awaitingClient' => Publication::with(['content', 'account'])->where('visible_to_client', true)
                ->when($clientId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $clientId)))
                ->where('status', 'planned')->where(fn ($q) => $q->whereNull('client_status')->orWhere('client_status', 'changes'))
                ->orderBy('scheduled_at')->limit(5)->get(),
        ]);
    }
}
