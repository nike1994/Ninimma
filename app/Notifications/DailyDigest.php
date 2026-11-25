<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param Collection $items [ticket_id, title, count] */
    public function __construct(public Collection $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('mail.digest_subject'))->line(__('mail.digest_intro'));
        foreach ($this->items as $item) {
            $mail->line('#'.$item['ticket_id'].' '.$item['title'].' — '.trans_choice('mail.digest_count', $item['count'], ['n' => $item['count']]));
        }

        return $mail->action(__('mail.open_panel'), route('dashboard'));
    }
}
