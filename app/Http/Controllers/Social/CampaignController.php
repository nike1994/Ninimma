<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        return view('social.campaigns.index', [
            'campaigns' => Campaign::with('client')->withCount('contents')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByRaw("case status when 'active' then 0 when 'review' then 1 when 'draft' then 2 else 3 end")
                ->latest('starts_on')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(new Campaign(['client_id' => $request->integer('client') ?: null, 'starts_on' => today()]));
    }

    public function edit(Campaign $campaign)
    {
        return $this->form($campaign->load(['contents.publications.account']));
    }

    private function form(Campaign $campaign)
    {
        return view('social.campaigns.form', ['campaign' => $campaign, 'clients' => Client::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $campaign = Campaign::create($this->validated($request));

        return redirect()->route('social.campaigns.edit', $campaign)->with('status', __('ui.saved'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->update($this->validated($request));

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'budget' => ['required', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(Campaign::STATUSES)],
            'revenue' => ['nullable', 'numeric', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
