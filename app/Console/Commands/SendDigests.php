<?php

namespace App\Console\Commands;

use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;

class SendDigests extends Command
{
    protected $signature = 'agency:digests';

    protected $description = 'Dzienne podsumowanie wiadomości dla osób, które je wybrały';

    public function handle(): int
    {
        User::where('notify_mode', 'daily')->where('is_active', true)->each(function (User $user) {
            $since = $user->last_digest_at ?? now()->subDay();
            $messages = TicketMessage::with('ticket')
                ->where('created_at', '>', $since)
                ->where('user_id', '!=', $user->id)
                ->when(! $user->isAdmin(), fn ($q) => $q->where('is_internal', false)
                    ->whereHas('ticket', fn ($t) => $t->where('client_id', $user->client_id)))
                ->when($user->isAdmin(), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', 'client')))
                ->get();

            if ($messages->isNotEmpty()) {
                $items = $messages->groupBy('ticket_id')->map(fn ($group) => [
                    'ticket_id' => $group->first()->ticket_id,
                    'title' => $group->first()->ticket->title,
                    'count' => $group->count(),
                ])->values();
                $user->notify(new DailyDigest($items));
            }
            $user->forceFill(['last_digest_at' => now()])->save();
        });

        return self::SUCCESS;
    }
}
