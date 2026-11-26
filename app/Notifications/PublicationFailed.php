<?php

namespace App\Notifications;

use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Automatyczna publikacja nie wyszła albo czeka (zespół publikuje ręcznie lub poprawia). */
class PublicationFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Publication $publication, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->publication;

        return (new MailMessage)
            ->subject(__('api.mail_subject', ['platform' => $p->platform->label(), 'title' => $p->content->title]))
            ->line(__('api.mail_line', ['account' => $p->account->name, 'date' => $p->scheduled_at->format('d.m H:i')]))
            ->line($this->reason)
            ->action(__('api.open_content'), route('social.contents.edit', $p->content_id));
    }
}
