<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Ticket $ticket, TicketService $tickets)
    {
        $this->authorize('reply', $ticket);
        $data = $request->validate([
            'body' => ['required_without:uploads', 'nullable', 'string', 'max:20000'],
            'internal' => ['boolean'],
            'uploads' => ['array'],
            'uploads.*' => ['integer'],
        ]);

        $message = $tickets->postMessage($ticket, $request->user(), (string) ($data['body'] ?? ''),
            $request->boolean('internal'), $data['uploads'] ?? []);

        if ($request->expectsJson()) {
            return response()->json(['id' => $message->id]);
        }

        return redirect()->to(route('tickets.show', $ticket).'#m'.$message->id);
    }
}
