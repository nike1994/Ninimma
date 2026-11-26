<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Services\InboundMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Potwierdzenie dla klienta: zgłoszenie z e-maila założone (dalsze odpowiedzi trafią do tego zlecenia). */
class TicketReceivedByEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return InboundMail::decorate((new MailMessage)
            ->subject(__('mail.inbound_received_subject', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->line(__('mail.inbound_received_line'))
            ->action(__('mail.open_ticket'), route('tickets.show', $this->ticket)), $this->ticket);
    }
}
