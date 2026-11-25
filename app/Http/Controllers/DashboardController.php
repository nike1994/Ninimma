<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\SystemLog;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $base = Ticket::visibleTo($user)->open()->with(['client', 'reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('last_activity_at');

        if ($user->isAdmin()) {
            $tickets = $base->get();

            return view('dashboard.admin', [
                'byBall' => $tickets->groupBy(fn (Ticket $t) => $t->status->ball()),
                'stalled' => $tickets->filter->isStalled(),
                'clients' => Client::where('is_active', true)->orderBy('name')->get(),
                'lastCron' => Cache::get('agency:last_cron'),
                'recentErrors' => SystemLog::where('level', 'error')->where('created_at', '>', now()->subDays(7))->count(),
            ]);
        }

        $tickets = $base->get();

        return view('dashboard.client', [
            'waitingForMe' => $tickets->filter(fn (Ticket $t) => $t->status->waitsForClient()),
            'inProgress' => $tickets->reject(fn (Ticket $t) => $t->status->waitsForClient()),
            'recentlyDone' => Ticket::visibleTo($user)->where('status', TicketStatus::Done->value)
                ->orderByDesc('closed_at')->limit(5)->get(),
        ]);
    }
}
