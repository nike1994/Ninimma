<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Ticket;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $quotes) {}

    public function store(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'hours_limit' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'is_diagnosis' => ['nullable', 'boolean'],
            'options' => ['array', 'max:6'],
            'options.*.label' => ['nullable', 'string', 'max:120'],
            'options.*.description' => ['nullable', 'string', 'max:500'],
            'options.*.hours' => ['nullable', 'numeric', 'min:0'],
            'options.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->quotes->create($ticket, $request->user(), $data);

        return back()->with('status', __('ui.quote_sent'));
    }

    public function decide(Request $request, Quote $quote)
    {
        $this->authorize('view', $quote->ticket);
        abort_unless($quote->status === 'pending', 422);
        $data = $request->validate(['decision' => ['required', 'in:accept,reject'], 'option' => ['nullable', 'integer', 'min:0']]);
        $option = isset($data['option']) && isset($quote->options[$data['option']]) ? (int) $data['option'] : null;
        abort_if($data['decision'] === 'accept' && $quote->options && $option === null, 422);
        $this->quotes->decide($quote, $request->user(), $data['decision'] === 'accept', $option);

        return back()->with('status', __('ui.decision_saved'));
    }

    public function requestHandover(Request $request, Ticket $ticket)
    {
        $this->quotes->requestHandover($ticket, $request->user());

        return back()->with('status', __('ui.handover_requested'));
    }

    public function acceptHandover(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        abort_unless($ticket->handover_requested_at && ! $ticket->accepted_at, 422);
        $this->quotes->acceptHandover($ticket, $request->user());

        return back()->with('status', __('ui.handover_done'));
    }
}
