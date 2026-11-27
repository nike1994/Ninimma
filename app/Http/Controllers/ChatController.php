<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Okno wiadomości dostępne na każdej stronie (panel z boku albo osobne okno): lista rozmów (zleceń) z ostatnią
 * wiadomością i nieprzeczytanymi, rozmowa i odpowiedź. Wiadomości i wysyłka korzystają z endpointów zleceń.
 */
class ChatController extends Controller
{
    public function threads(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');
        // Tylko zlecenia z rozmową (zadania bez wiadomości nie zaśmiecają czatu); kolejność wg ostatniej wiadomości.
        $col = $user->isAdmin() ? 'last_message_id' : 'last_public_message_id';
        $base = Ticket::visibleTo($user)->where('archive_state', 'active')->whereNotNull($col)
            ->with(['client', 'reads' => fn ($q) => $q->where('user_id', $user->id)]);

        $unreadTotal = (clone $base)->open()->orderByDesc($col)->limit(60)->get()->filter(fn ($t) => $t->hasUnreadFor($user))->count();
        if ($request->boolean('count')) {
            return response()->json(['unread_total' => $unreadTotal]);
        }

        $tickets = $base->search($request->query('q'), $user)
            ->when($filter === 'ours', fn ($q) => $q->whereIn('status', $user->isAdmin()
                ? [TicketStatus::WithAgency, TicketStatus::Blocked] : [TicketStatus::WaitingClient, TicketStatus::NeedsDecision]))
            ->when($filter !== 'done', fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', TicketStatus::Done)->orWhere('closed_at', '>=', now()->subDays(14))))
            ->orderByDesc($col)->limit($filter === 'unread' ? 120 : 40)->get();
        if ($filter === 'unread') {
            $tickets = $tickets->filter(fn ($t) => $t->hasUnreadFor($user))->values();
        }
        $last = TicketMessage::with('user')->whereIn('id', $tickets->map(fn ($t) => $t->lastVisibleMessageId($user))->filter())->get()->keyBy('id');

        return response()->json([
            'unread_total' => $unreadTotal,
            'threads' => $tickets->map(function (Ticket $t) use ($user, $last) {
                $m = $last[$t->lastVisibleMessageId($user)] ?? null;

                return [
                    'id' => $t->id, 'title' => $t->title, 'client' => $user->isAdmin() ? $t->client->name : null,
                    'status' => $t->status->value, 'status_label' => $t->status->label(), 'unread' => $t->hasUnreadFor($user),
                    'last' => $m ? ['author' => $m->user_id === $user->id ? __('chat.you') : Str::before($m->user?->name ?? '—', ' '),
                        'text' => Str::limit(preg_replace('/\s+/u', ' ', $m->body) ?: __('chat.attachment'), 90), 'internal' => $m->is_internal] : null,
                    'at' => $this->when($m?->created_at ?? $t->last_activity_at),
                    'url' => route('tickets.show', $t), 'poll' => route('tickets.poll', $t), 'send' => route('tickets.messages.store', $t),
                    'can_reply' => $user->can('reply', $t),
                ];
            })->values(),
        ]);
    }

    /** Osobne okno czatu (do postawienia obok innej karty). */
    public function window()
    {
        return view('chat.window');
    }

    private function when($at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->isToday() ? $at->format('H:i') : ($at->gt(now()->subDays(6)) ? $at->translatedFormat('D') : $at->format('d.m'));
    }
}
