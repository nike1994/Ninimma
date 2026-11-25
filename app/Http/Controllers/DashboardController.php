<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\FileUsage;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tickets = Ticket::visibleTo($user)->open()->where('archive_state', 'active')
            ->with(['client', 'reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('last_activity_at')->get();

        if ($user->isAdmin()) {
            $period = in_array($request->query('period'), DashboardStats::PERIODS, true) ? $request->query('period') : '7d';
            $stats = new DashboardStats($user, $period);
            $tickets->load('assignee');
            $upcoming = $tickets->filter(fn (Ticket $t) => $t->due_on && ! $t->due_on->isBefore(today()))->sortBy('due_on')->take(4);

            return view('dashboard.admin', [
                'stats' => $stats,
                'period' => $period,
                'cards' => $stats->cards($tickets),
                'conversations' => $stats->conversations(),
                'activity' => $stats->activity(),
                'board' => $stats->board($tickets),
                'priorities' => DashboardStats::sortByUrgency($tickets->filter(fn ($t) => $t->priority->isElevated() || $t->isOverdue()))->take(4),
                'upcoming' => $upcoming,
                'dueToday' => $tickets->first(fn (Ticket $t) => $t->due_on?->isToday()),
                'stalled' => $tickets->filter->isStalled(),
                'team' => User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get(),
                'lastCron' => Cache::get('agency:last_cron'),
                'recentErrors' => SystemLog::where('level', 'error')->where('created_at', '>', now()->subDays(7))->count(),
            ]);
        }

        return view('dashboard.client', [
            'tickets' => $tickets,
            'waitingForMe' => $tickets->filter(fn (Ticket $t) => $t->status->waitsForClient()),
            'unread' => $tickets->filter(fn ($t) => $t->hasUnreadFor($user)),
            'files' => self::visibleFiles($user)->latest('id')->limit(4)->get(),
            'newFilesCount' => self::visibleFiles($user)->where('file_usages.created_at', '>', now()->subDays(7))->count(),
            'messages' => TicketMessage::with(['user', 'ticket'])->where('is_internal', false)
                ->whereHas('ticket', fn ($q) => $q->where('client_id', $user->client_id))
                ->where('user_id', '!=', $user->id)->latest('id')->limit(3)->get(),
            'recentlyDone' => Ticket::visibleTo($user)->where('status', TicketStatus::Done->value)
                ->orderByDesc('closed_at')->limit(3)->get(),
        ]);
    }

    /** Załączniki z wiadomości, które użytkownik może zobaczyć. */
    public static function visibleFiles(User $user)
    {
        return FileUsage::with(['file', 'usable.ticket', 'uploader'])
            ->where('usable_type', 'ticket_message')
            ->whereHasMorph('usable', [TicketMessage::class], function ($q) use ($user) {
                if (! $user->isAdmin()) {
                    $q->where('is_internal', false)->whereHas('ticket', fn ($t) => $t->where('client_id', $user->client_id));
                }
            });
    }
}
