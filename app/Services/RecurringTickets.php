<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Models\User;

class RecurringTickets
{
    public function __construct(private TicketService $tickets) {}

    public function createFromTemplate(TicketTemplate $template, int $clientId, ?User $by = null): Ticket
    {
        $by ??= User::where('role', 'admin')->orderBy('id')->firstOrFail();
        $title = strtr($template->title, [
            '{month}' => now()->translatedFormat('F Y'),
            '{date}' => now()->toDateString(),
        ]);

        return $this->tickets->create($by, [
            'client_id' => $clientId,
            'title' => $title,
            'type' => $template->type->value,
            'template_id' => $template->id,
        ], $template->body);
    }

    /** Tworzy zaległe zlecenia cykliczne; zwraca liczbę utworzonych. */
    public function runDue(): int
    {
        $count = 0;
        $due = TicketTemplate::where('is_active', true)->where('recurrence', '!=', 'none')
            ->whereNotNull('client_id')->whereDate('next_run_on', '<=', today())->get();
        foreach ($due as $template) {
            $this->createFromTemplate($template, $template->client_id);
            $template->advance();
            $count++;
        }

        return $count;
    }
}
