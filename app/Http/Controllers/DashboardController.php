<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\FileUsage;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
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
            return view('dashboard.admin', [
                'byBall' => $tickets->groupBy(fn (Ticket $t) => $t->status->ball()),
                'stalled' => $tickets->filter->isStalled(),
                'unread' => $tickets->filter(fn ($t) => $t->hasUnreadFor($user)),
                'clientsCount' => Client::where('is_active', true)->count(),
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
