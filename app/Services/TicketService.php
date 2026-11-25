<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\FileUsage;
use App\Models\Read;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketMessagePosted;
use App\Notifications\TicketNeedsClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TicketService
{
    public function create(User $author, array $data, ?string $body, array $uploadIds = []): Ticket
    {
        return DB::transaction(function () use ($author, $data, $body, $uploadIds) {
            $ticket = Ticket::create([
                'client_id' => $author->isAdmin() ? $data['client_id'] : $author->client_id,
                'parent_id' => $data['parent_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'title' => $data['title'],
                'type' => $data['type'] ?? 'task',
                'status' => TicketStatus::WithAgency,
                'created_by' => $author->id,
                'priority' => $author->isAdmin() ? ($data['priority'] ?? 'normal') : 'normal',
                'due_on' => $author->isAdmin() ? ($data['due_on'] ?? null) : null,
                'assigned_to' => $author->isAdmin() ? ($data['assigned_to'] ?? null) : null,
            ]);
            $ticket->forceFill(['last_activity_at' => now()])->save();
            $this->event($ticket, $author, 'created');
            if ($ticket->parent_id) {
                $this->event($ticket->parent, $author, 'child_created', ['id' => $ticket->id, 'title' => $ticket->title]);
            }
            if (filled($body) || $uploadIds) {
                $this->postMessage($ticket, $author, (string) $body, false, $uploadIds);
                // Opis zlecenia to nie rozpoczęcie pracy.
                $ticket->forceFill(['started_at' => null])->save();
            }

            return $ticket;
        });
    }

    public function postMessage(Ticket $ticket, User $author, string $body, bool $internal, array $uploadIds = []): TicketMessage
    {
        $internal = $internal && $author->isAdmin();

        $message = DB::transaction(function () use ($ticket, $author, $body, $internal, $uploadIds) {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $internal,
            ]);
            app(FileStore::class)->attachUploads($uploadIds, $author, $message);

            $ticket->last_message_id = $message->id;
            if (! $internal) {
                $ticket->last_public_message_id = $message->id;
            }
            $ticket->last_activity_at = now();

            // Pierwsza odpowiedź agencji oznacza, że praca się rozpoczęła.
            if ($author->isAdmin() && ! $internal && $ticket->started_at === null) {
                $ticket->started_at = now();
            }

            // Odpowiedź klienta przerywa zastój i oddaje piłkę agencji.
            if (! $author->isAdmin()) {
                $ticket->stalled_at = null;
                $ticket->reminders_sent = 0;
                if ($ticket->status->waitsForClient() && $ticket->status !== TicketStatus::NeedsDecision) {
                    $this->applyStatus($ticket, $author, TicketStatus::WithAgency, null);
                }
            }
            $ticket->save();
            $this->markRead($ticket, $author);

            return $message;
        });

        if (! $internal) {
            $this->notifyOtherSide($ticket, $author, new TicketMessagePosted($message));
        }

        return $message;
    }

    public function changeStatus(Ticket $ticket, User $by, TicketStatus $status, ?string $reason = null): void
    {
        if ($ticket->status === $status && $ticket->blocked_reason === $reason) {
            return;
        }
        DB::transaction(function () use ($ticket, $by, $status, $reason) {
            $this->applyStatus($ticket, $by, $status, $reason);
            $ticket->last_activity_at = now();
            $ticket->save();
        });

        if ($status->waitsForClient()) {
            $this->notifyClient($ticket, new TicketNeedsClient($ticket, 0));
        }
    }

    private function applyStatus(Ticket $ticket, User $by, TicketStatus $status, ?string $reason): void
    {
        $from = $ticket->status;
        $ticket->status = $status;
        $ticket->blocked_reason = $status === TicketStatus::Blocked ? $reason : null;
        $ticket->closed_at = $status === TicketStatus::Done ? now() : null;
        if ($status->waitsForClient()) {
            $ticket->waiting_since = now();
            $ticket->reminders_sent = 0;
            $ticket->stalled_at = null;
        } else {
            $ticket->waiting_since = null;
            $ticket->stalled_at = null;
        }
        $this->event($ticket, $by, 'status_changed', array_filter([
            'from' => $from?->value, 'to' => $status->value, 'reason' => $ticket->blocked_reason,
        ]));
    }

    /** Priorytet, termin i osoba prowadząca (tylko administrator). */
    public function updateDetails(Ticket $ticket, User $by, array $data): void
    {
        DB::transaction(function () use ($ticket, $by, $data) {
            $before = ['priority' => $ticket->priority->value, 'due_on' => $ticket->due_on?->toDateString(), 'assigned_to' => $ticket->assigned_to];
            $ticket->fill([
                'priority' => $data['priority'] ?? $ticket->priority->value,
                'due_on' => $data['due_on'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);
            if (! $ticket->isDirty()) {
                return;
            }
            $ticket->save();
            if ($before['priority'] !== $ticket->priority->value) {
                $this->event($ticket, $by, 'priority_changed', ['priority' => $ticket->priority->value]);
            }
            if ($before['due_on'] !== $ticket->due_on?->toDateString()) {
                $this->event($ticket, $by, 'due_changed', ['date' => $ticket->due_on?->format('d.m.Y') ?? '—']);
            }
            if ($before['assigned_to'] !== $ticket->assigned_to) {
                $this->event($ticket, $by, 'assigned', ['name' => $ticket->assignee?->name ?? '—'], true);
            }
        });
    }

    public function logTime(Ticket $ticket, User $by, int $minutes, string $date, ?string $note): void
    {
        DB::transaction(function () use ($ticket, $by, $minutes, $date, $note) {
            $ticket->timeEntries()->create(['user_id' => $by->id, 'minutes' => $minutes, 'spent_on' => $date, 'note' => $note]);
            if ($ticket->started_at === null) {
                $ticket->started_at = now();
            }
            $ticket->last_activity_at = now();
            $ticket->save();
            $this->event($ticket, $by, 'time_logged', ['time' => \App\Models\TimeEntry::format($minutes)], true);
        });
    }

    /** Ręczne lub automatyczne przypomnienie dla klienta. */
    public function remind(Ticket $ticket, ?User $by, bool $automatic = false): void
    {
        $ticket->reminders_sent++;
        $ticket->last_reminder_at = now();
        $ticket->save();
        $this->event($ticket, $by, $automatic ? 'auto_reminder' : 'reminder', ['n' => $ticket->reminders_sent]);
        $this->notifyClient($ticket, new TicketNeedsClient($ticket, $ticket->reminders_sent));
    }

    public function markRead(Ticket $ticket, User $user): void
    {
        Read::updateOrCreate(
            ['user_id' => $user->id, 'readable_type' => $ticket->getMorphClass(), 'readable_id' => $ticket->id],
            ['last_seen_id' => $ticket->lastVisibleMessageId($user), 'seen_at' => now()],
        );
    }

    public function event(Ticket $ticket, ?User $user, string $type, array $data = [], bool $internal = false): TicketEvent
    {
        return $ticket->events()->create([
            'user_id' => $user?->id, 'type' => $type, 'data' => $data ?: null, 'is_internal' => $internal,
        ]);
    }

    /** Czytelnicy po stronie klienta: kto i do której wiadomości doczytał. */
    public function clientReads(Ticket $ticket): Collection
    {
        return $ticket->reads()->with('user')->get()->filter(fn (Read $r) => $r->user && ! $r->user->isAdmin());
    }

    private function notifyOtherSide(Ticket $ticket, User $author, $notification): void
    {
        $recipients = $author->isAdmin()
            ? $this->clientUsers($ticket)
            : User::where('role', Role::Admin)->where('is_active', true)->get();

        $recipients = $recipients->where('id', '!=', $author->id)->where('notify_mode', 'each');
        Notification::send($recipients, $notification);
    }

    private function notifyClient(Ticket $ticket, $notification): void
    {
        Notification::send($this->clientUsers($ticket), $notification);
    }

    private function clientUsers(Ticket $ticket): Collection
    {
        return User::where('client_id', $ticket->client_id)->where('role', Role::Client)->where('is_active', true)->get();
    }
}
