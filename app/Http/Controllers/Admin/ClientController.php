<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index()
    {
        return view('admin.clients.index', [
            'clients' => Client::withCount(['users', 'tickets', 'tickets as open_tickets_count' => fn ($q) => $q->open()])
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('admin.clients.show', $client);
    }

    public function show(Request $request, Client $client)
    {
        $all = $client->tickets()->get(['id', 'status', 'last_activity_at']);
        $tickets = $client->tickets()->with(['client', 'reads' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->search($request->query('q'), $request->user())
            ->when($request->query('status'), fn ($q, $s) => $s === 'open' ? $q->open() : $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('last_activity_at')->paginate(15)->withQueryString();

        return view('admin.clients.show', [
            'client' => $client->load(['users', 'invitations' => fn ($q) => $q->whereNull('accepted_at')->latest()]),
            'tickets' => $tickets,
            'counts' => [
                'active' => $all->filter(fn ($t) => $t->status->ball() === 'agency')->count(),
                'waiting' => $all->filter(fn ($t) => $t->status->waitsForClient())->count(),
                'done' => $all->filter(fn ($t) => $t->status->value === 'done')->count(),
            ],
            'lastActivity' => $all->max('last_activity_at'),
        ]);
    }

    public function edit(Client $client)
    {
        return view('admin.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));

        return redirect()->route('admin.clients.show', $client)->with('status', __('ui.saved'));
    }

    public function invite(Request $request, Client $client)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'locale' => ['required', Rule::in(config('agency.locales'))],
        ]);
        [$invitation, $token] = Invitation::issue($data + [
            'role' => Role::Client->value, 'client_id' => $client->id, 'invited_by' => $request->user()->id,
        ]);
        Notification::route('mail', $invitation->email)
            ->notify((new InvitationNotification($token, $invitation->name))->locale($invitation->locale));

        return back()->with('status', __('ui.invitation_sent'));
    }

    public function toggleUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422);
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
