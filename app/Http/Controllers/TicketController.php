<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::visibleTo($user)
            ->with(['client', 'reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->search($request->query('q'), $user);

        if ($status = $request->query('status')) {
            $status === 'open' ? $query->open() : $query->where('status', $status);
        }
        if ($user->isAdmin() && ($clientId = $request->integer('client'))) {
            $query->where('client_id', $clientId);
        }
        if ($request->query('archive') !== '1') {
            $query->where('archive_state', 'active');
        }

        return view('tickets.index', [
            'tickets' => $query->orderByDesc('last_activity_at')->paginate(30)->withQueryString(),
            'clients' => $user->isAdmin() ? Client::orderBy('name')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $parent = $request->integer('parent')
            ? Ticket::visibleTo($user)->find($request->integer('parent'))
            : null;

        return view('tickets.create', [
            'admins' => $user->isAdmin() ? \App\Models\User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get() : collect(),
            'clients' => $user->isAdmin() ? Client::where('is_active', true)->orderBy('name')->get() : collect(),
            'parent' => $parent,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(TicketType::class)],
            'client_id' => [Rule::requiredIf($user->isAdmin()), 'nullable', 'exists:clients,id'],
            'parent_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
            'body' => ['nullable', 'string', 'max:20000'],
            'uploads' => ['array'],
            'uploads.*' => ['integer'],
        ]);
        if (! empty($data['parent_id'])) {
            $parent = Ticket::visibleTo($user)->findOrFail($data['parent_id']);
            $data['client_id'] = $parent->client_id;
        }

        $ticket = $this->tickets->create($user, $data, $data['body'] ?? null, $data['uploads'] ?? []);

        return redirect()->route('tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $user = $request->user();

        $messages = $ticket->messages()->with(['user', 'attachments.file'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('is_internal', false))
            ->orderBy('id')->get();
        $events = $ticket->events()->with('user')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')->get();

        $clientReads = $this->tickets->clientReads($ticket);
        $this->tickets->markRead($ticket, $user);

        return view('tickets.show', [
            'ticket' => $ticket->load(['client', 'parent', 'children', 'author', 'assignee']),
            'admins' => $user->isAdmin() ? \App\Models\User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get() : collect(),
            'timeEntries' => $user->isAdmin() ? $ticket->timeEntries()->with('user')->latest('spent_on')->latest('id')->get() : collect(),
            'quotes' => $ticket->quotes()->with(['author', 'decider'])->get(),
            'boards' => $ticket->boards()->with('latestVersion')->get(),
            'costDocuments' => $ticket->costDocuments()->with('pdf')->get(),
            'messages' => $messages,
            'events' => $events,
            'clientReads' => $clientReads,
            'lastId' => (int) $messages->max('id'),
        ]);
    }

    /** Odpytywanie: nowe wiadomości od podanego id + stan zlecenia. */
    public function poll(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $user = $request->user();
        $after = $request->integer('after');

        $messages = $ticket->messages()->with(['user', 'attachments.file'])
            ->where('id', '>', $after)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('is_internal', false))
            ->orderBy('id')->get();

        if ($messages->isNotEmpty()) {
            $this->tickets->markRead($ticket, $user);
        }

        return response()->json([
            'html' => $messages->map(fn (TicketMessage $m) => view('tickets._message', ['message' => $m, 'me' => $user])->render())->implode(''),
            'last_id' => (int) ($messages->max('id') ?? $after),
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'seen' => $user->isAdmin() ? $this->tickets->clientReads($ticket)->map(fn ($r) => [
                'name' => $r->user->name, 'last_seen_id' => $r->last_seen_id, 'at' => $r->seen_at->format('Y-m-d H:i'),
            ])->values() : [],
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'blocked_reason' => ['nullable', 'required_if:status,blocked', 'string', 'max:250'],
        ]);
        $this->tickets->changeStatus($ticket, $request->user(), TicketStatus::from($data['status']), $data['blocked_reason'] ?? null);

        return back()->with('status', __('ui.saved'));
    }

    public function details(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
            'estimate' => ['nullable', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
        ]);
        $this->tickets->updateDetails($ticket, $request->user(), $data);
        if ($request->has('estimate')) {
            $ticket->forceFill(['estimate_minutes' => filled($data['estimate'] ?? null) ? self::parseHours($data['estimate']) : null])->save();
        }

        return back()->with('status', __('ui.saved'));
    }

    public function logTime(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'hours' => ['required', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
            'spent_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);
        $minutes = self::parseHours($data['hours']);
        abort_if($minutes < 1 || $minutes > 24 * 60, 422);
        $this->tickets->logTime($ticket, $request->user(), $minutes, $data['spent_on'], $data['note'] ?? null, ! $request->boolean('internal'));
        $pct = app(\App\Services\QuoteService::class)->checkLimit($ticket, $request->user());

        return back()->with('status', $pct !== null && $pct >= 80 ? __('ui.limit_warning', ['pct' => $pct]) : __('ui.saved'));
    }

    /** "1:30" | "1,5" | "2" → minuty */
    public static function parseHours(string $value): int
    {
        if (str_contains($value, ':')) {
            [$h, $m] = explode(':', $value);

            return (int) $h * 60 + (int) $m;
        }

        return (int) round((float) str_replace(',', '.', $value) * 60);
    }

    public function remind(Request $request, Ticket $ticket)
    {
        $this->tickets->remind($ticket, $request->user());

        return back()->with('status', __('ui.reminder_sent'));
    }
}
