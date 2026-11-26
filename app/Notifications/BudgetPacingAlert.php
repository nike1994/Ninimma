<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class BudgetPacingAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Collection $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('mkt.pace_mail_subject', ['n' => $this->items->count()]))->line(__('mkt.pace_mail_intro'));
        foreach ($this->items as $i) {
            $mail->line('• '.__('mkt.pace_mail_line', $i));
        }

        return $mail->action(__('mkt.open'), route('social.campaigns.index'));
    }
}
