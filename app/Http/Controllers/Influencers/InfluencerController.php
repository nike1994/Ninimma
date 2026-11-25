<?php

namespace App\Http\Controllers\Influencers;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\FileUsage;
use App\Models\Influencer;
use Illuminate\Http\Request;

class InfluencerController extends Controller
{
    public function index(Request $request)
    {
        $view = in_array($request->query('view'), ['grid', 'board', 'table'], true) ? $request->query('view') : 'grid';
        $influencers = Influencer::with(['photo', 'collaborations.campaign'])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('tags', 'like', "%{$t}%")))
            ->when($request->query('tag'), fn ($q, $t) => $q->where('tags', 'like', '%"'.$t.'"%'))
            ->orderByDesc('is_active')->orderBy('name')->get();

        $collabs = Collaboration::with(['influencer.photo', 'campaign.client', 'shipments', 'materials'])
            ->when($request->integer('campaign'), fn ($q, $c) => $q->where('campaign_id', $c))
            ->when($request->query('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'active')))
            ->get();
        $filter = $request->query('filter');
        if ($filter === 'not_delivered') {
            $collabs = $collabs->filter(fn ($c) => $c->needs_shipment && ! $c->shipmentsDelivered());
        } elseif ($filter === 'missing_materials') {
            $collabs = $collabs->filter(fn ($c) => ! $c->materials_complete_at);
        } elseif ($filter === 'unpaid') {
            $collabs = $collabs->filter(fn ($c) => $c->published_at && ! $c->paid_at);
        } elseif ($filter === 'license_expiring') {
            $collabs = $collabs->filter(fn ($c) => $c->license_to && $c->license_to->lte(today()->addDays(30)));
        }

        return view('influencers.index', [
            'view' => $view,
            'influencers' => $influencers,
            'collabs' => $collabs->values(),
            'campaigns' => Campaign::orderBy('name')->get(),
            'tags' => $influencers->flatMap(fn ($i) => $i->tags ?? [])->unique()->sort()->values(),
        ]);
    }

    public function create()
    {
        return view('influencers.form', ['influencer' => new Influencer(['profiles' => [], 'tags' => []])]);
    }

    public function store(Request $request)
    {
        $influencer = Influencer::create($this->validated($request));
        $this->attachPhoto($request, $influencer);

        return redirect()->route('influencers.show', $influencer)->with('status', __('ui.saved'));
    }

    public function show(Influencer $influencer)
    {
        return view('influencers.show', [
            'influencer' => $influencer->load(['photo', 'collaborations.campaign.client', 'collaborations.shipments', 'collaborations.materials', 'reminders' => fn ($q) => $q->orderBy('is_done')->orderBy('due_on')]),
            'campaigns' => Campaign::where('status', '!=', 'finished')->with('client')->orderBy('name')->get(),
        ]);
    }

    public function edit(Influencer $influencer)
    {
        return view('influencers.form', compact('influencer'));
    }

    public function update(Request $request, Influencer $influencer)
    {
        $influencer->update($this->validated($request));
        $this->attachPhoto($request, $influencer);

        return redirect()->route('influencers.show', $influencer)->with('status', __('ui.saved'));
    }

    private function attachPhoto(Request $request, Influencer $influencer): void
    {
        $id = $request->input('uploads.0');
        $usage = $id ? FileUsage::where('id', $id)->where('uploaded_by', $request->user()->id)->whereNull('usable_id')->first() : null;
        if ($usage && $usage->file->isImage()) {
            $usage->update(['usable_type' => 'influencer', 'usable_id' => $influencer->id]);
            $influencer->update(['photo_usage_id' => $usage->id]);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:64'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'parcel_locker' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'string', 'max:300'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'profiles' => ['array', 'max:8'],
            'profiles.*.platform' => ['nullable', \Illuminate\Validation\Rule::enum(Platform::class)],
            'profiles.*.url' => ['nullable', 'url', 'max:255'],
            'profiles.*.handle' => ['nullable', 'string', 'max:100'],
            'profiles.*.followers' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim(mb_strtolower($t)))->filter()->unique()->values()->all();
        $data['profiles'] = collect($data['profiles'] ?? [])->filter(fn ($p) => ! empty($p['url']) || ! empty($p['handle']))->values()->all();
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
