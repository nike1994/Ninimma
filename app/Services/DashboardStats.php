<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Read;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Dane pulpitu administratora liczone z prawdziwych rekordów dla wybranego okresu. */
class DashboardStats
{
    public const PERIODS = ['7d', '30d', 'month', 'prev_month'];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public CarbonImmutable $prevFrom;

    public CarbonImmutable $prevTo;

    public function __construct(public User $user, public string $period = '7d')
    {
        $now = CarbonImmutable::now();
        [$this->from, $this->to] = match ($period) {
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'prev_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
        $days = (int) $this->from->diffInDays($this->to) + 1;
        $this->prevTo = $this->from->subSecond();
        $this->prevFrom = $this->prevTo->subDays($days - 1)->startOfDay();
    }

    public static function trend(int|float $current, int|float $previous): ?int
    {
        return $previous == 0 ? null : (int) round(($current - $previous) / $previous * 100);
    }

    private function clientMessages(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return TicketMessage::whereBetween('created_at', [$from, $to])
            ->whereHas('user', fn ($q) => $q->where('role', Role::Client->value))->count();
    }

    private function minutes(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) TimeEntry::whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())->sum('minutes');
    }

    public function cards(Collection $open): array
    {
        $msgs = $this->clientMessages($this->from, $this->to);
        $msgsPrev = $this->clientMessages($this->prevFrom, $this->prevTo);
        $created = Ticket::whereBetween('created_at', [$this->from, $this->to])->count();
        $createdPrev = Ticket::whereBetween('created_at', [$this->prevFrom, $this->prevTo])->count();
        $min = $this->minutes($this->from, $this->to);
        $minPrev = $this->minutes($this->prevFrom, $this->prevTo);
        $today = $open->filter(fn (Ticket $t) => $t->due_on && ! $t->due_on->isAfter(today()));
        $review = $open->filter(fn (Ticket $t) => $t->status->waitsForClient());

        return [
            ['icon' => 'message', 'label' => __('ui.kpi_messages'), 'value' => $msgs, 'trend' => self::trend($msgs, $msgsPrev), 'note' => __('ui.vs_previous')],
            ['icon' => 'check-square', 'label' => __('ui.kpi_open'), 'value' => $open->count(), 'trend' => self::trend($created, $createdPrev), 'note' => __('ui.kpi_new_in_period', ['n' => $created])],
            ['icon' => 'calendar', 'label' => __('ui.kpi_today'), 'value' => $today->count(), 'trend' => null, 'note' => __('ui.kpi_overdue', ['n' => $today->filter->isOverdue()->count()])],
            ['icon' => 'layers', 'label' => __('ui.kpi_review'), 'value' => $review->count(), 'trend' => null, 'note' => __('ui.kpi_stalled', ['n' => $review->filter->isStalled()->count()])],
            ['icon' => 'clock', 'label' => __('ui.kpi_hours'), 'value' => TimeEntry::hours($min), 'trend' => self::trend($min, $minPrev), 'note' => __('ui.vs_previous')],
        ];
    }

    /** Ostatnie rozmowy z klientami: po jednej na zlecenie, z liczbą nieprzeczytanych. */
    public function conversations(int $limit = 4): Collection
    {
        $latest = TicketMessage::with(['user', 'ticket.client'])
            ->where('is_internal', false)
            ->whereHas('user', fn ($q) => $q->where('role', Role::Client->value))
            ->latest('id')->limit(60)->get()->unique('ticket_id')->take($limit);

        $reads = Read::where('user_id', $this->user->id)->where('readable_type', 'ticket')
            ->whereIn('readable_id', $latest->pluck('ticket_id'))->pluck('last_seen_id', 'readable_id');

        return $latest->map(function (TicketMessage $m) use ($reads) {
            $m->unread = TicketMessage::where('ticket_id', $m->ticket_id)->where('id', '>', (int) ($reads[$m->ticket_id] ?? 0))
                ->whereHas('user', fn ($q) => $q->where('role', Role::Client->value))->count();

            return $m;
        })->values();
    }

    /** Wykres aktywności zespołu: wiadomości i działania w zleceniach na dzień. */
    public function activity(): array
    {
        $days = collect();
        for ($d = $this->from; $d <= $this->to && $d <= CarbonImmutable::now()->endOfDay(); $d = $d->addDay()) {
            $days->push($d->toDateString());
        }
        $messages = TicketMessage::whereBetween('created_at', [$this->from, $this->to])->get(['created_at'])
            ->countBy(fn ($m) => $m->created_at->toDateString());
        $actions = TicketEvent::whereBetween('created_at', [$this->from, $this->to])
            ->whereHas('user', fn ($q) => $q->where('role', Role::Admin->value))->get(['created_at'])
            ->countBy(fn ($e) => $e->created_at->toDateString());

        return $days->map(fn ($day) => [
            'date' => $day,
            'label' => CarbonImmutable::parse($day)->translatedFormat($days->count() <= 7 ? 'D' : 'j'),
            'title' => CarbonImmutable::parse($day)->translatedFormat('D, j M'),
            'messages' => $messages[$day] ?? 0,
            'actions' => $actions[$day] ?? 0,
        ])->values()->all();
    }

    /** Tablica zadań: otwarte zlecenia + zakończone w okresie, w czterech kolumnach. */
    public function board(Collection $open): array
    {
        $done = Ticket::with(['client', 'assignee'])->where('status', TicketStatus::Done->value)
            ->whereBetween('closed_at', [$this->from, $this->to])->latest('closed_at')->get();

        $columns = ['todo' => collect(), 'doing' => collect(), 'review' => collect(), 'done' => $done];
        foreach (self::sortByUrgency($open) as $t) {
            $columns[$t->boardColumn()]->push($t);
        }

        return $columns;
    }

    public static function sortByUrgency(Collection $tickets): Collection
    {
        $rank = ['urgent' => 0, 'high' => 1, 'normal' => 2];

        return $tickets->sortBy([
            fn ($a, $b) => $rank[$a->priority->value] <=> $rank[$b->priority->value],
            fn ($a, $b) => ($a->due_on?->timestamp ?? PHP_INT_MAX) <=> ($b->due_on?->timestamp ?? PHP_INT_MAX),
        ])->values();
    }

    public function chartLabel(): string
    {
        return $this->from->translatedFormat('j M Y').' – '.$this->to->translatedFormat('j M Y');
    }
}
