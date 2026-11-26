<?php

namespace App\Notifications;

use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketMessagePosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->message->ticket;

        return \App\Services\InboundMail::decorate((new MailMessage)
            ->subject(__('mail.message_subject', ['id' => $ticket->id, 'title' => $ticket->title]))
            ->line(__('mail.message_intro', ['name' => $this->message->user?->name ?? '—']))
            ->line(Str::limit($this->message->body, 600))
            ->action(__('mail.open_ticket'), route('tickets.show', $ticket)), $ticket);
    }
}
