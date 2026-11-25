<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Wycena jako limit pracy: klient akceptuje limit, nie obietnicę efektu. Praca ponad limit wymaga nowej decyzji. */
class QuoteService
{
    public function __construct(private TicketService $tickets) {}

    public function create(Ticket $ticket, User $by, array $data): Quote
    {
        $quote = DB::transaction(function () use ($ticket, $by, $data) {
            // Poprzednie oczekujące wyceny zostają w historii jako zastąpione.
            $ticket->quotes()->where('status', 'pending')->update(['status' => 'superseded']);
            $options = collect($data['options'] ?? [])->filter(fn ($o) => filled($o['label'] ?? null))
                ->map(fn ($o) => ['label' => $o['label'], 'description' => $o['description'] ?? null,
                    'hours' => filled($o['hours'] ?? null) ? (float) str_replace(',', '.', $o['hours']) : null,
                    'amount' => filled($o['amount'] ?? null) ? (float) str_replace(',', '.', $o['amount']) : null])->values()->all();

            $quote = $ticket->quotes()->create([
                'version' => (int) $ticket->quotes()->max('version') + 1,
                'hours_limit' => $data['hours_limit'] ?? null,
                'amount' => $data['amount'] ?? null,
                'justification' => $data['justification'] ?? null,
                'options' => $options ?: null,
                'is_diagnosis' => ! empty($data['is_diagnosis']),
                'created_by' => $by->id,
            ]);
            $this->tickets->event($ticket, $by, 'quote_created', ['v' => $quote->version]);

            return $quote;
        });
        // Zmiana zakresu: zlecenie czeka na decyzję klienta (powiadomienie + przypomnienia).
        $this->tickets->changeStatus($ticket, $by, TicketStatus::NeedsDecision);

        return $quote;
    }

    public function decide(Quote $quote, User $by, bool $accept, ?int $option = null): void
    {
        DB::transaction(function () use ($quote, $by, $accept, $option) {
            $label = $option !== null ? ($quote->options[$option]['label'] ?? null) : null;
            $quote->forceFill([
                'status' => $accept ? 'accepted' : 'rejected',
                'chosen_option' => $accept ? $option : null,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ])->save();
            $this->tickets->event($quote->ticket, $by, $accept ? 'quote_accepted' : 'quote_rejected', array_filter(['v' => $quote->version, 'option' => $label]));
        });
        $this->tickets->changeStatus($quote->ticket, $by, TicketStatus::WithAgency);
    }

    /** Ostrzeżenie przy 80% zaakceptowanego limitu (raz na wycenę). */
    public function checkLimit(Ticket $ticket, User $by): ?int
    {
        $quote = $ticket->acceptedQuote();
        $limit = $quote?->acceptedMinutes();
        if (! $limit) {
            return null;
        }
        $pct = (int) floor($ticket->minutesLogged() / $limit * 100);
        if ($pct >= 80 && ! $ticket->events()->where('type', 'limit_warning')->where('data->v', $quote->version)->exists()) {
            $this->tickets->event($ticket, $by, 'limit_warning', ['v' => $quote->version, 'pct' => $pct], true);
        }

        return $pct;
    }

    public function requestHandover(Ticket $ticket, User $by): void
    {
        $ticket->forceFill(['handover_requested_at' => now()])->save();
        $this->tickets->event($ticket, $by, 'handover_requested');
        $this->tickets->changeStatus($ticket, $by, TicketStatus::WaitingClient);
    }

    public function acceptHandover(Ticket $ticket, User $by): void
    {
        $ticket->forceFill(['accepted_at' => now(), 'accepted_by' => $by->id])->save();
        $this->tickets->event($ticket, $by, 'handover_accepted');
        $this->tickets->changeStatus($ticket, $by, TicketStatus::Done);
    }
}
