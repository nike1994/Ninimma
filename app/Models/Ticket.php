<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    use HasFactory;

    protected $attributes = ['priority' => 'normal', 'archive_state' => 'active', 'reminders_sent' => 0];

    protected $fillable = ['client_id', 'parent_id', 'template_id', 'title', 'type', 'status', 'blocked_reason', 'created_by', 'priority', 'due_on', 'assigned_to'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => Priority::class,
            'due_on' => 'date',
            'started_at' => 'datetime',
            'type' => TicketType::class,
            'last_activity_at' => 'datetime',
            'waiting_since' => 'datetime',
            'last_reminder_at' => 'datetime',
            'stalled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null && $this->status !== TicketStatus::Done && $this->due_on->isBefore(today());
    }

    /** Kolumna tablicy zadań: todo | doing | review | done. */
    public function boardColumn(): string
    {
        return match (true) {
            $this->status === TicketStatus::Done => 'done',
            $this->status->waitsForClient() => 'review',
            $this->started_at !== null || $this->status === TicketStatus::Blocked => 'doing',
            default => 'todo',
        };
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function reads(): MorphMany
    {
        return $this->morphMany(Read::class, 'readable');
    }

    /** Zlecenia widoczne dla użytkownika (izolacja klientów). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('client_id', $user->client_id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', TicketStatus::Done->value);
    }

    public function scopeSearch(Builder $query, ?string $term, User $user): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like, $user) {
            $q->where('title', 'like', $like)
                ->orWhereHas('messages', function (Builder $m) use ($like, $user) {
                    $m->where('body', 'like', $like);
                    if (! $user->isAdmin()) {
                        $m->where('is_internal', false);
                    }
                });
            if (ctype_digit(trim($like, '%'))) {
                $q->orWhere('id', (int) trim($like, '%'));
            }
        });
    }

    public function lastVisibleMessageId(User $user): int
    {
        return (int) ($user->isAdmin() ? $this->last_message_id : $this->last_public_message_id);
    }

    /** Czy użytkownik ma nieprzeczytane nowości (kropka). */
    public function hasUnreadFor(User $user): bool
    {
        $last = $this->lastVisibleMessageId($user);
        if ($last === 0) {
            return false;
        }
        $read = $this->relationLoaded('reads')
            ? $this->reads->firstWhere('user_id', $user->id)
            : $this->reads()->where('user_id', $user->id)->first();

        return ! $read || $read->last_seen_id < $last;
    }

    public function isStalled(): bool
    {
        return $this->stalled_at !== null && $this->status->waitsForClient();
    }
}
