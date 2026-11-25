<?php

namespace App\Http\Controllers;

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
            'ticket' => $ticket->load(['client', 'parent', 'children', 'author']),
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

    public function remind(Request $request, Ticket $ticket)
    {
        $this->tickets->remind($ticket, $request->user());

        return back()->with('status', __('ui.reminder_sent'));
    }
}
