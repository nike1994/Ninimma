<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LicenseAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Collection $items, public int $conflicts) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('lic.mail_subject', ['n' => $this->items->count()]))->line(__('lic.mail_intro'));
        foreach ($this->items as $i) {
            $mail->line('• '.$i['material'].' — '.$i['who'].' ('.$i['campaign'].'): '.__('lic.mail_until', ['date' => $i['until'], 'days' => $i['days']]));
        }
        if ($this->conflicts) {
            $mail->line(__('lic.mail_conflicts', ['n' => $this->conflicts]));
        }

        return $mail->action(__('lic.open'), route('licenses.index'));
    }
}
