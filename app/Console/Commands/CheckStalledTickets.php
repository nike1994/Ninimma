<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Console\Command;

/**
 * Sygnał zastoju: przypomnienie następnego dnia, jeśli klient nie przeczytał;
 * kolejne po tygodniu, jeśli nie odpowiedział — wtedy zlecenie trafia na listę zastojów.
 */
class CheckStalledTickets extends Command
{
    protected $signature = 'agency:stall-check';

    protected $description = 'Wysyła przypomnienia klientom i oznacza zastoje';

    public function handle(TicketService $service): int
    {
        $tickets = Ticket::whereIn('status', [TicketStatus::WaitingClient->value, TicketStatus::NeedsDecision->value])
            ->whereNotNull('waiting_since')->where('archive_state', 'active')->with('reads.user')->get();

        foreach ($tickets as $ticket) {
            $hours = $ticket->waiting_since->diffInHours(now());
            $lastPublic = (int) $ticket->last_public_message_id;
            $clientSeen = $ticket->reads->contains(fn ($r) => $r->user && ! $r->user->isAdmin()
                && $r->last_seen_id >= $lastPublic && $r->seen_at >= $ticket->waiting_since);

            if ($ticket->reminders_sent === 0 && $hours >= config('agency.stall_first_reminder_hours') && ! $clientSeen) {
                $service->remind($ticket, null, true);
            } elseif ($ticket->stalled_at === null && $hours >= config('agency.stall_second_reminder_days') * 24) {
                $service->remind($ticket, null, true);
                $ticket->forceFill(['stalled_at' => now()])->save();
                $service->event($ticket, null, 'stalled', [], true);
            }
        }

        return self::SUCCESS;
    }
}
