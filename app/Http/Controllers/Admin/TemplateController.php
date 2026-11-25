<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\TicketTemplate;
use App\Services\RecurringTickets;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index()
    {
        return view('admin.templates', [
            'templates' => TicketTemplate::with('client')->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'editing' => request()->integer('edit') ? TicketTemplate::find(request()->integer('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        TicketTemplate::create($this->validated($request));

        return redirect()->route('admin.templates.index')->with('status', __('ui.saved'));
    }

    public function update(Request $request, TicketTemplate $template)
    {
        $template->update($this->validated($request));

        return redirect()->route('admin.templates.index')->with('status', __('ui.saved'));
    }

    public function destroy(TicketTemplate $template)
    {
        $template->delete();

        return back()->with('status', __('ui.deleted'));
    }

    /** Ręczne utworzenie zlecenia z szablonu (np. dla innego klienta). */
    public function run(Request $request, TicketTemplate $template, RecurringTickets $recurring)
    {
        $clientId = $template->client_id ?? $request->validate(['client_id' => ['required', 'exists:clients,id']])['client_id'];
        $ticket = $recurring->createFromTemplate($template, (int) $clientId, $request->user());

        return redirect()->route('tickets.show', $ticket);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(TicketType::class)],
            'body' => ['nullable', 'string', 'max:20000'],
            'client_id' => ['nullable', 'exists:clients,id', 'required_unless:recurrence,none'],
            'recurrence' => ['required', Rule::in(['none', 'weekly', 'monthly'])],
            'next_run_on' => ['nullable', 'date', 'required_unless:recurrence,none'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active', true)];
    }
}
