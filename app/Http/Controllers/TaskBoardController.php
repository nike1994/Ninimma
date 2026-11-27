<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TicketStatus;
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
            ->when($request->integer('campaign'), fn ($q, $c) => $q->where('campaign_id', $c))
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
            'sets' => \App\Models\TaskSet::orderByDesc('uses')->orderBy('name')->get(),
            'campaigns' => \App\Models\Campaign::whereIn('status', ['draft', 'review', 'active'])->orderBy('name')->get(['id', 'name', 'client_id']),
            'defaultClient' => $clientId ?? $request->session()->get('task_client'),
            'campaign' => $request->integer('campaign') ? \App\Models\Campaign::find($request->integer('campaign')) : null,
        ]);
    }

    /** Szybkie dodawanie: jedna linijka albo wklejona lista (każda linijka = zadanie), zapis jak w TaskParser. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'lines' => ['required', 'string', 'max:20000'],
            'client_id' => ['nullable', 'exists:clients,id'],
        ]);
        $request->session()->put('task_client', $data['client_id'] ?? null);
        $items = \App\Services\TaskSetService::parser($request->user())->parseMany($data['lines'], $data['client_id'] ?? null);
        $bad = collect($items)->filter(fn ($i) => $i['errors']);
        if ($bad->isNotEmpty()) {
            return back()->withInput()->withErrors(['lines' => $bad->first()['title'].': '.$bad->first()['errors'][0]]);
        }
        $n = app(\App\Services\TaskSetService::class)->createMany($request->user(), $items);

        return back()->with('status', trans_choice('task.added_n', $n, ['n' => $n]));
    }

    /** Podgląd rozpoznania (JSON) — pokazujemy, co program zrozumiał, zanim zapiszesz. */
    public function parse(Request $request)
    {
        $data = $request->validate(['lines' => ['nullable', 'string', 'max:20000'], 'client_id' => ['nullable', 'integer'], 'start_on' => ['nullable', 'date']]);
        $base = filled($data['start_on'] ?? null) ? \Carbon\Carbon::parse($data['start_on'])->startOfDay() : null;
        $clients = Client::where('is_active', true)->get(['id', 'name'])->keyBy('id');
        $team = User::where('role', 'admin')->where('is_active', true)->get(['id', 'name'])->keyBy('id');

        return response()->json(collect(\App\Services\TaskSetService::parser($request->user(), $base)->parseMany((string) ($data['lines'] ?? ''), $data['client_id'] ?? null))
            ->map(fn ($i) => [
                'title' => $i['title'], 'client' => $clients[$i['client_id']]->name ?? null, 'person' => $team[$i['assigned_to']]->name ?? null,
                'due' => $i['due_on']?->translatedFormat('D j M'), 'estimate' => $i['estimate'] ? TimeEntry::format($i['estimate']) : null,
                'priority' => $i['priority'] !== 'normal' ? Priority::from($i['priority'])->label() : null, 'errors' => $i['errors'],
            ]));
    }



    // ---------------------------------------------------------------- zestawy zadań

    public function storeSet(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:10000']]);
        \App\Models\TaskSet::create($data);

        return back()->with('status', __('ui.saved'));
    }

    public function updateSet(Request $request, \App\Models\TaskSet $set)
    {
        $set->update($request->validate(['name' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:10000']]));

        return back()->with('status', __('ui.saved'));
    }

    public function destroySet(\App\Models\TaskSet $set)
    {
        $set->delete();

        return back()->with('status', __('ui.deleted'));
    }

    /** Wstawienie zestawu dla klienta: terminy „+N” liczone od daty startu, opcjonalnie przypięte do kampanii. */
    public function applySet(Request $request, \App\Models\TaskSet $set)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'start_on' => ['required', 'date'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('client_id', $request->input('client_id'))],
        ]);
        $n = app(\App\Services\TaskSetService::class)->apply($set, (int) $data['client_id'], \Carbon\Carbon::parse($data['start_on']), $request->user(), $data['campaign_id'] ?? null);

        return redirect()->route('tasks.index', array_filter(['who' => 'all', 'client' => $data['client_id'], 'campaign' => $data['campaign_id'] ?? null]))
            ->with('status', __('task.set_applied', ['set' => $set->name, 'n' => $n]));
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
