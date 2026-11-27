<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stoper pracy: jeden na osobę, zapisany w bazie (działa po zamknięciu przeglądarki, bez stałych procesów).
 * Zatrzymanie zapisuje wpis czasu przy zleceniu. Zapomniany stoper ucinamy do MAX_MINUTES.
 */
class TimeTracker
{
    public const MAX_MINUTES = 10 * 60;

    public function __construct(private TicketService $tickets) {}

    /** Start przy zleceniu; działający stoper przy innym zleceniu najpierw się zapisuje. Zwraca zapisane minuty poprzedniego. */
    public function start(User $user, Ticket $ticket): ?int
    {
        $saved = null;
        if ($user->timer_ticket_id && $user->timer_ticket_id !== $ticket->id) {
            $saved = $this->stop($user)['minutes'] ?? null;
        }
        if ($user->timer_ticket_id !== $ticket->id) {
            $user->forceFill(['timer_ticket_id' => $ticket->id, 'timer_started_at' => now()])->save();
        }

        return $saved;
    }

    /** Zatrzymanie i zapis. Zwraca [minutes, capped, ticket] albo null, gdy stoper nie działał. */
    public function stop(User $user, ?string $note = null, bool $billable = true): ?array
    {
        $ticket = $user->timer_ticket_id ? Ticket::find($user->timer_ticket_id) : null;
        if (! $ticket || ! $user->timer_started_at) {
            $this->reset($user);

            return null;
        }
        $elapsed = (int) ceil($user->timer_started_at->diffInSeconds(now()) / 60);
        $minutes = max(1, min($elapsed, self::MAX_MINUTES));
        DB::transaction(function () use ($user, $ticket, $minutes, $note, $billable) {
            $this->tickets->logTime($ticket, $user, $minutes, $user->timer_started_at->toDateString(), $note, $billable, 'timer');
            $this->reset($user);
        });

        return ['minutes' => $minutes, 'capped' => $elapsed > self::MAX_MINUTES, 'ticket' => $ticket];
    }

    public function discard(User $user): void
    {
        $this->reset($user);
    }

    public static function elapsedMinutes(User $user): int
    {
        return $user->timer_started_at ? (int) floor($user->timer_started_at->diffInSeconds(now()) / 60) : 0;
    }

    /** Dni tygodnia (pon–nd) z wpisami osoby: [ticket_id => [date => minuty]] + zlecenia. */
    public static function week(User $user, \Carbon\CarbonInterface $monday): array
    {
        $entries = TimeEntry::with('ticket.client')->where('user_id', $user->id)
            ->whereBetween('spent_on', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])->get();
        $rows = $entries->groupBy('ticket_id')->map(fn (Collection $es) => [
            'ticket' => $es->first()->ticket,
            'days' => $es->groupBy(fn ($e) => $e->spent_on->toDateString())->map->sum('minutes')->all(),
            'total' => (int) $es->sum('minutes'),
        ])->sortByDesc('total')->values();

        return ['rows' => $rows, 'entries' => $entries->sortByDesc('spent_on')->values()];
    }

    private function reset(User $user): void
    {
        $user->forceFill(['timer_ticket_id' => null, 'timer_started_at' => null])->save();
    }
}
