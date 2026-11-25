<?php

namespace App\Providers;

use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'ticket' => Ticket::class,
            'ticket_message' => TicketMessage::class,
        ]);

        // Dziennik systemu: nieudane wysyłki e-maili i zadania z kolejki.
        Event::listen(JobFailed::class, function (JobFailed $e) {
            SystemLog::record('job_failed', mb_substr($e->exception->getMessage(), 0, 250), [
                'job' => $e->job->resolveName(),
            ], 'error');
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $e) {
            SystemLog::record('mail_failed', 'Notification failed', [
                'notification' => $e->notification::class,
                'notifiable' => $e->notifiable->email ?? null,
            ], 'error');
        });
    }
}
