<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.invite_subject', ['app' => config('app.name')]))
            ->greeting(__('mail.hello', ['name' => $this->name]))
            ->line(__('mail.invite_line', ['app' => config('app.name')]))
            ->action(__('mail.invite_action'), route('invitation.show', $this->token))
            ->line(__('mail.invite_expires', ['days' => config('agency.invitation_valid_days')]));
    }
}
