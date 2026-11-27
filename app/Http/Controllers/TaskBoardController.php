<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TicketService;
use App\Services\TimeTracker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Zadania zespołu (wszystko jest zleceniem): lista wg terminów albo tablica wg statusu, filtr osoby i klienta, stoper. */
class TaskBoardController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request)
    {
        $me = $request->user();
        $who = $request->query('who', 'me');
        $view = $request->query('view') === 'board' ? 'board' : 'list';
        $clientId = $request->integer('client') ?: null;

        $base = Ticket::with(['client', 'assignee'])->withSum('timeEntries as minutes', 'minutes')
            ->where('archive_state', 'active')
            ->when($who === 'me', fn ($q) => $q->where('assigned_to', $me->id))
            ->when($who === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit((string) $who), fn ($q) => $q->where('assigned_to', (int) $who))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'));

        $open = (clone $base)->open()->orderByRaw('due_on is null')->orderBy('due_on')
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 else 2 end")->orderByDesc('last_activity_at')->limit(300)->get();
        $doneRecent = (clone $base)->where('status', TicketStatus::Done)->where('closed_at', '>=', now()->subDays(7))->latest('closed_at')->limit(30)->get();

        $today = today();
        $endWeek = today()->endOfWeek();
        $groups = [
            'overdue' => $open->filter(fn ($t) => $t->due_on && $t->due_on->lt($today)),
            'today' => $open->filter(fn ($t) => $t->due_on && $t->due_on->isSameDay($today)),
            'week' => $open->filter(fn ($t) => $t->due_on && $t->due_on->gt($today) && $t->due_on->lte($endWeek)),
            'later' => $open->filter(fn ($t) => $t->due_on && $t->due_on->gt($endWeek)),
            'nodate' => $open->filter(fn ($t) => ! $t->due_on),
        ];
        $columns = [
            'with_agency' => $open->where('status', TicketStatus::WithAgency),
            'client' => $open->filter(fn ($t) => $t->status->waitsForClient()),
            'blocked' => $open->where('status', TicketStatus::Blocked),
            'done' => $doneRecent,
        ];
        $monday = today()->startOfWeek();

        return view('tasks.index', [
            'groups' => $groups, 'columns' => $columns, 'view' => $view, 'who' => $who, 'clientId' => $clientId,
            'team' => User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'kpi' => [
                'mine' => Ticket::open()->where('archive_state', 'active')->where('assigned_to', $me->id)->count(),
                'overdue' => $groups['overdue']->count(),
                'today' => $groups['today']->count(),
                'waiting' => $columns['client']->count(),
                'week_minutes' => (int) TimeEntry::where('user_id', $me->id)->where('spent_on', '>=', $monday->toDateString())->sum('minutes'),
            ],
            'me' => $me,
        ]);
    }

    /** Szybkie dodanie zadania (zlecenie typu „zadanie” bez opisu — rozmowa zaczyna się w zleceniu). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'client_id' => ['required', 'exists:clients,id'],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'estimate' => ['nullable', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
            'type' => ['nullable', Rule::enum(TicketType::class)],
        ]);
        $ticket = $this->tickets->create($request->user(), $data + ['type' => $data['type'] ?? 'task', 'assigned_to' => $data['assigned_to'] ?? $request->user()->id], null);
        if (filled($data['estimate'] ?? null)) {
            $ticket->forceFill(['estimate_minutes' => TicketController::parseHours($data['estimate'])])->save();
        }

        return back()->with('status', __('task.added'));
    }

    /** Zmiana z listy: osoba, termin, priorytet, szacunek; „zrobione” zamyka zlecenie. */
    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
            'due_on' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'estimate' => ['nullable', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
            'done' => ['nullable', 'boolean'],
        ]);
        if ($request->has('done')) {
            $this->tickets->changeStatus($ticket, $request->user(), $request->boolean('done') ? TicketStatus::Done : TicketStatus::WithAgency);
        }
        $details = array_intersect_key($data, array_flip(['assigned_to', 'due_on', 'priority']));
        if ($details) {
            $this->tickets->updateDetails($ticket, $request->user(), array_merge([
                'priority' => $ticket->priority->value, 'due_on' => $ticket->due_on?->toDateString(), 'assigned_to' => $ticket->assigned_to,
            ], array_filter($details, fn ($v, $k) => $k !== 'priority' || $v !== null, ARRAY_FILTER_USE_BOTH)));
        }
        if ($request->has('estimate')) {
            $ticket->forceFill(['estimate_minutes' => filled($data['estimate']) ? TicketController::parseHours($data['estimate']) : null])->save();
        }

        return back()->with('status', __('ui.saved'));
    }

    public function timerStart(Request $request, Ticket $ticket, TimeTracker $timer)
    {
        abort_if($ticket->status === TicketStatus::Done, 422);
        $saved = $timer->start($request->user(), $ticket);

        return back()->with('status', $saved ? __('task.timer_switched', ['time' => TimeEntry::format($saved)]) : __('task.timer_started'));
    }

    public function timerStop(Request $request, TimeTracker $timer)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);
        $r = $timer->stop($request->user(), $data['note'] ?? null, ! $request->boolean('internal'));
        if (! $r) {
            return back();
        }
        $msg = __('task.timer_saved', ['time' => TimeEntry::format($r['minutes']), 'task' => $r['ticket']->title]);

        return back()->with($r['capped'] ? 'error' : 'status', $r['capped'] ? $msg.' '.__('task.timer_capped', ['h' => TimeTracker::MAX_MINUTES / 60]) : $msg);
    }

    public function timerDiscard(Request $request, TimeTracker $timer)
    {
        $timer->discard($request->user());

        return back()->with('status', __('task.timer_discarded'));
    }
}
