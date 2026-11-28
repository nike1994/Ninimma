<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CostDocument;
use App\Models\FileUsage;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Dokumenty kosztowe: faktury wystawiane w zewnętrznym programie (KSeF) — w panelu PDF, kwota i status. */
class BillingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $docs = CostDocument::with(['client', 'ticket', 'pdf'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('client_id', $user->client_id))
            ->when($user->isAdmin() && $request->integer('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
            ->when($request->query('state') === 'unpaid', fn ($q) => $q->where('is_paid', false))
            ->latest('issued_on')->paginate(30)->withQueryString();

        return view('billing.index', [
            'docs' => $docs,
            'clients' => $user->isAdmin() ? Client::orderBy('name')->get() : collect(),
            'quotes' => Quote::with('ticket')->where('status', 'accepted')
                ->whereHas('ticket', fn ($q) => $user->isAdmin() ? $q : $q->where('client_id', $user->client_id))
                ->latest('decided_at')->limit(10)->get(),
            // Suma ze wszystkich dokumentów w bieżącym filtrze, nie tylko z widocznej strony.
            'unpaidTotal' => CostDocument::query()
                ->when(! $user->isAdmin(), fn ($q) => $q->where('client_id', $user->client_id))
                ->when($user->isAdmin() && $request->integer('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
                ->where('is_paid', false)->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'ticket_id' => ['nullable', Rule::exists('tickets', 'id')->where('client_id', $request->input('client_id'))],
            'number' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'uploads' => ['array', 'max:1'],
            'uploads.*' => ['integer'],
        ]);
        $doc = CostDocument::create(Arr::except($data, 'uploads') + ['is_paid' => $request->boolean('is_paid'), 'paid_on' => $request->boolean('is_paid') ? today() : null]);
        if ($id = $data['uploads'][0] ?? null) {
            $usage = FileUsage::where('id', $id)->where('uploaded_by', $request->user()->id)->whereNull('usable_id')->first();
            if ($usage) {
                $usage->update(['usable_type' => 'cost_document', 'usable_id' => $doc->id]);
                $doc->update(['file_usage_id' => $usage->id]);
            }
        }

        return back()->with('status', __('ui.saved'));
    }

    public function togglePaid(CostDocument $document)
    {
        $document->update(['is_paid' => ! $document->is_paid, 'paid_on' => $document->is_paid ? null : today()]);

        return back()->with('status', __('ui.saved'));
    }
}
