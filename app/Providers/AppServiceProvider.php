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
use Illuminate\Support\Facades\View;
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
            'content' => \App\Models\Content::class,
            'board' => \App\Models\Board::class,
            'cost_document' => \App\Models\CostDocument::class,
            'influencer' => \App\Models\Influencer::class,
            'collaboration' => \App\Models\Collaboration::class,
            'material' => \App\Models\Material::class,
        ]);

        // Mini-lista zleceń w panelu bocznym.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $tickets = Ticket::visibleTo($user)->open()->where('archive_state', 'active')
                ->with(['client', 'reads' => fn ($q) => $q->where('user_id', $user->id)])
                ->orderByDesc('last_activity_at')->limit(30)->get();
            $view->with('sidebarTickets', $tickets->take(3));
            $view->with('unreadCount', $tickets->filter(fn ($t) => $t->hasUnreadFor($user))->count());
        });

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
