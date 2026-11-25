<?php

namespace App\Http\Controllers\Social;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Zadania social media = zlecenia typu "social" (zgodnie z zasadą: wszystko jest zleceniem). */
class TaskController extends Controller
{
    public function store(Request $request, TicketService $tickets)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'client_id' => ['required', 'exists:clients,id'],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
        ]);
        $tickets->create($request->user(), $data + ['type' => 'social'], null);

        return back()->with('status', __('ui.saved'));
    }

    public function toggle(Request $request, Ticket $ticket, TicketService $tickets)
    {
        $tickets->changeStatus($ticket, $request->user(), $ticket->status === TicketStatus::Done ? TicketStatus::WithAgency : TicketStatus::Done);

        return back();
    }
}
