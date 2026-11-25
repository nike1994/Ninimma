<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Client;
use App\Services\AdService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Reklamy i promocje: lista z wykorzystaniem budżetu, formularz, wyniki płatne, import CSV. */
class AdController extends Controller
{
    public function __construct(private AdService $ads) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'current');
        $ads = AdCampaign::with(['client', 'campaign', 'publication.content', 'latestResult'])
            ->when($request->integer('client'), fn ($q, $c) => $q->where('client_id', $c))
            ->when($request->integer('campaign'), fn ($q, $c) => $q->where('campaign_id', $c))
            ->when($filter === 'current', fn ($q) => $q->whereIn('status', ['planned', 'active', 'paused']))
            ->when($filter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($filter === 'ending', fn ($q) => $q->where('status', 'active')->whereNotNull('ends_on')->whereDate('ends_on', '<=', today()->addDays(3)))
            ->when($filter === 'finished', fn ($q) => $q->where('status', 'finished'))
            ->orderByRaw("case status when 'active' then 0 when 'planned' then 1 when 'paused' then 2 else 3 end")
            ->orderBy('ends_on')->get();

        return view('social.ads.index', [
            'ads' => $ads,
            'summary' => AdService::aggregate($ads),
            'filter' => $filter,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(new AdCampaign([
            'client_id' => $request->integer('client') ?: null, 'campaign_id' => $request->integer('campaign') ?: null,
            'material_id' => $request->integer('material') ?: null,
            'platform' => 'meta', 'starts_on' => today(), 'ends_on' => today()->addDays(14),
        ]));
    }

    public function edit(AdCampaign $ad)
    {
        return $this->form($ad->load(['results.user', 'publication.content', 'campaign.utmLinks']));
    }

    private function form(AdCampaign $ad)
    {
        return view('social.ads.form', [
            'ad' => $ad,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'campaigns' => Campaign::where('status', '!=', 'finished')->orWhere('id', $ad->campaign_id)->orderBy('name')->get(),
            'metrics' => $ad->exists ? AdService::metrics($ad->totals()) : null,
            'materials' => \App\Services\LicenseGuard::pickerMaterials(array_filter([$ad->material_id])),
            'licenseIssues' => $ad->exists ? app(\App\Services\LicenseGuard::class)->allIssues()->where('ad_id', $ad->id)->values() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $ad = AdCampaign::create($this->validated($request));

        return redirect()->route('social.ads.edit', $ad)->with('status', __('ui.saved'));
    }

    public function update(Request $request, AdCampaign $ad)
    {
        $ad->update($this->validated($request));

        return back()->with('status', __('ui.saved'));
    }

    public function destroy(AdCampaign $ad)
    {
        $ad->publication?->forceFill(['promoted' => false])->save();
        $ad->delete();

        return redirect()->route('social.ads.index')->with('status', __('ui.deleted'));
    }

    public function storeResult(Request $request, AdCampaign $ad)
    {
        $data = $request->validate([
            'measured_on' => ['required', 'date'],
            'spend' => ['required', 'numeric', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'conversions' => ['nullable', 'integer', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->ads->addResult($ad, $request->user(), $data);
        if ($ad->status === 'planned') {
            $ad->update(['status' => 'active']);
        }

        return back()->with('status', __('ads.result_saved'));
    }

    /** Import eksportu z menedżera reklam (jedna reklama albo dopasowanie po nazwie). */
    public function import(Request $request, ?AdCampaign $ad = null)
    {
        $data = $request->validate(['csv' => ['required', 'file', 'max:4096'], 'measured_on' => ['nullable', 'date']]);
        [$saved, $unmatched] = $this->ads->importCsv($request->file('csv')->get(), $request->user(), $ad?->exists ? $ad : null, $data['measured_on'] ?? null);

        return back()->with('status', trans_choice('ads.imported', $saved, ['n' => $saved]))
            ->with('unmatched', $unmatched);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('client_id', $request->input('client_id'))],
            'platform' => ['required', Rule::in(AdCampaign::PLATFORMS)],
            'name' => ['required', 'string', 'max:200'],
            'external_name' => ['nullable', 'string', 'max:200'],
            'objective' => ['required', Rule::in(AdCampaign::OBJECTIVES)],
            'budget' => ['required', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(AdCampaign::STATUSES)],
            'target_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'material_id' => ['nullable', 'exists:materials,id'],
        ]) + ['material_id' => null];
    }
}
