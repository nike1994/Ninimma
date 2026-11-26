<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Powiadomienie, że piłka jest po stronie klienta (0 = od razu, 1+ = kolejne przypomnienia). */
class TicketNeedsClient extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public int $reminder) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->reminder === 0 ? 'mail.needs_client_subject' : 'mail.reminder_subject';

        return \App\Services\InboundMail::decorate((new MailMessage)
            ->subject(__($key, ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->line(__('mail.needs_client_line', ['status' => $this->ticket->status->label()]))
            ->action(__('mail.open_ticket'), route('tickets.show', $this->ticket)), $this->ticket);
    }
}
