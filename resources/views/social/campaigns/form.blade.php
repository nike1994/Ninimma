@extends('layouts.app')
@section('title', $campaign->exists ? $campaign->name : __('social.new_campaign'))
@section('content')
<h1 class="page-title">{{ $campaign->exists ? $campaign->name : __('social.new_campaign') }}</h1>
<div class="grid-main">
    <div>
    <form method="post" action="{{ $campaign->exists ? route('social.campaigns.update', $campaign) : route('social.campaigns.store') }}" class="card stack">
        @csrf @if($campaign->exists) @method('put') @endif
        <div class="form-row">
            <label class="field"><span>{{ __('ui.client') }}</span>
                <select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $campaign->client_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </label>
            <label class="field"><span>{{ __('social.status') }}</span>
                <select name="status">@foreach(\App\Models\Campaign::STATUSES as $st)<option value="{{ $st }}" @selected(old('status', $campaign->status) === $st)>{{ __('social.cmp_'.$st) }}</option>@endforeach</select>
            </label>
            <x-field name="budget" type="number" step="0.01" min="0" :label="__('social.budget_pln')" :value="$campaign->budget" required />
        </div>
        <x-field name="name" :label="__('social.campaign_name')" :value="$campaign->name" required />
        <div class="form-row">
            <label class="field"><span>{{ __('mkt.objective') }}</span>
                <select name="objective"><option value="">{{ __('mkt.objective_auto') }}</option>@foreach(\App\Models\Campaign::OBJECTIVES as $o)<option value="{{ $o }}" @selected(old('objective', $campaign->objective) === $o)>{{ __('mkt.obj_'.$o) }}</option>@endforeach</select></label>
            <x-field name="target_value" type="number" step="0.01" min="0" :label="__('mkt.target_label')" :value="$campaign->target_value" />
            <div></div>
        </div>
        <p class="muted small">{{ __('mkt.target_form_hint') }}</p>
        <x-field name="goal" type="textarea" :label="__('social.goal')" :value="$campaign->goal" rows="3" />
        <div class="form-row">
            <x-field name="starts_on" type="date" :label="__('social.starts_on')" :value="$campaign->starts_on?->toDateString()" />
            <x-field name="ends_on" type="date" :label="__('social.ends_on')" :value="$campaign->ends_on?->toDateString()" />
            <div></div>
        </div>
        <p class="muted small">{{ __('social.results_hint') }}</p>
        <div class="form-row">
            <x-field name="revenue" type="number" step="0.01" min="0" :label="__('social.revenue')" :value="$campaign->revenue" />
            <x-field name="leads" type="number" min="0" :label="__('social.leads')" :value="$campaign->leads" />
            <div></div>
        </div>
        <button class="btn btn-send"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>
    </form>
    @if($campaign->exists)
    <section class="card utm-card">
        <h2><x-icon name="link" /> {{ __('ads.utm_builder') }}</h2>
        <p class="muted small">{{ __('ads.utm_hint') }}</p>
        <form method="post" action="{{ route('social.utm.store', $campaign) }}" class="utm-form" data-utm-form>
            @csrf
            <input name="base_url" type="url" placeholder="https://sklep.pl/produkt" required class="utm-base">
            <input name="source" placeholder="utm_source (facebook)" required list="utm-sources">
            <input name="medium" placeholder="utm_medium (paid_social)" required list="utm-mediums">
            <input name="name" placeholder="utm_campaign" value="{{ \Illuminate\Support\Str::slug($campaign->name, '_') }}" required>
            <input name="content" placeholder="utm_content ({{ __('ads.optional') }})">
            <input name="term" placeholder="utm_term ({{ __('ads.optional') }})">
            <code class="utm-preview" data-utm-preview></code>
            <button class="btn btn-dark btn-small">{{ __('ads.utm_save') }}</button>
            <datalist id="utm-sources"><option value="facebook"><option value="instagram"><option value="google"><option value="tiktok"><option value="newsletter"><option value="influencer"></datalist>
            <datalist id="utm-mediums"><option value="paid_social"><option value="cpc"><option value="social"><option value="email"><option value="influencer"><option value="display"></datalist>
        </form>
        @foreach($campaign->utmLinks as $u)
            <div class="utm-row"><code>{{ $u->full_url }}</code>
                <button type="button" class="btn btn-small" data-copy="{{ $u->full_url }}"><x-icon name="link" :size="13" /> {{ __('ads.copy') }}</button>
                <form method="post" action="{{ route('social.utm.destroy', $u) }}">@csrf @method('delete')<button class="btn btn-small">✕</button></form></div>
        @endforeach
    </section>
    @endif
    </div>
    @if($campaign->exists)
    <aside>
        <section class="card card-dark">
            <x-waves variant="card" />
            @php($ins = new \App\Services\CampaignInsights($campaign))
            @php($tt = $ins->totals())
            @php($g = $ins->goal($tt))
            <h2>{{ __('social.results') }}</h2>
            <dl class="dark-dl">
                <dt>{{ __('social.budget_short') }}</dt><dd>{{ number_format((float) $campaign->budget, 0, ',', ' ') }} zł</dd>
                <dt>{{ __('mkt.reach') }}</dt><dd>{{ \App\Support\Num::short((int) $tt['reach']) }}</dd>
                @if($g->metric !== 'reach')<dt>{{ __('mkt.m_'.$g->metric) }}</dt><dd>{{ $g->format === 'money' ? number_format($g->value, 0, ',', ' ').' zł' : \App\Support\Num::short((int) $g->value) }}</dd>@endif
                @if($g->pct !== null)<dt>{{ __('mkt.goal_col') }}</dt><dd>{{ $g->pct }}%</dd>@endif
            </dl>
        </section>
        <section class="card">
            <h2><x-icon name="grid" /> {{ __('ads.campaign_budget') }}</h2>
            @include('social.ads._budget', ['spent' => $campaign->spent(), 'budget' => (float) $campaign->budget])
            <p class="muted small">{{ __('ads.campaign_budget_hint') }}</p>
        </section>
        <section class="card">
            <div class="card-head"><h2><x-icon name="zap" /> {{ __('ads.title') }}</h2>
                <a href="{{ route('social.ads.create', ['client' => $campaign->client_id, 'campaign' => $campaign->id]) }}" class="btn btn-small"><x-icon name="plus" :size="14" /></a></div>
            @forelse($campaign->ads()->with('latestResult')->get() as $a)
                <a href="{{ route('social.ads.edit', $a) }}" class="side-file"><span class="ad-platform ap-{{ $a->platform }}">{{ __('ads.platform_short_'.$a->platform) }}</span><span>{{ $a->name }}</span>
                    <small class="muted">{{ number_format($a->totals()['spend'], 0, ',', ' ') }} / {{ number_format((float) $a->budget, 0, ',', ' ') }} zł</small></a>
            @empty
                <p class="muted small">{{ __('ads.none') }}</p>
            @endforelse
        </section>
        <a href="{{ route('reports.campaign', $campaign) }}" class="btn btn-send btn-block" style="margin-bottom: 18px"><x-icon name="bar-chart" :size="18" />{{ __('inf.report') }}</a>
        <section class="card">
            <div class="card-head"><h2>{{ __('inf.collaborations') }}</h2><a href="{{ route('influencers.index') }}" class="btn btn-small"><x-icon name="plus" :size="14" /></a></div>
            @forelse($campaign->collaborations()->with(['influencer.photo', 'shipments'])->get() as $c)
                <a href="{{ route('collaborations.show', $c) }}" class="side-file">@include('influencers._photo', ['inf' => $c->influencer, 'size' => 28])<span>{{ $c->influencer->name }}</span><small class="muted">{{ __('inf.stage_'.$c->stage()) }}</small></a>
            @empty
                <p class="muted small">{{ __('inf.no_collabs') }}</p>
            @endforelse
        </section>
        <section class="card">
            <div class="card-head"><h2>{{ __('social.contents') }}</h2>
                <a href="{{ route('social.contents.create', ['client' => $campaign->client_id, 'campaign' => $campaign->id, 'stage' => 'preparing']) }}" class="btn btn-small"><x-icon name="plus" :size="14" /></a></div>
            @forelse($campaign->contents as $c)
                <a href="{{ route('social.contents.edit', $c) }}" class="side-file"><span>{{ $c->title }}</span>
                    @foreach($c->publications as $p)<x-platform :platform="$p->platform" :size="16" />@endforeach</a>
            @empty
                <p class="muted small">{{ __('social.no_campaign_contents') }}</p>
            @endforelse
        </section>
    </aside>
    @endif
</div>
@endsection
@push('scripts')
<script>
(function () {
    const f = document.querySelector('[data-utm-form]');
    if (f) {
        const preview = f.querySelector('[data-utm-preview]');
        const update = () => {
            const base = f.base_url.value.trim(); if (!base) { preview.textContent = ''; return; }
            const [url, hash] = base.split('#');
            const p = new URLSearchParams();
            [['utm_source', 'source'], ['utm_medium', 'medium'], ['utm_campaign', 'name'], ['utm_content', 'content'], ['utm_term', 'term']].forEach(([k, n]) => {
                const v = f[n].value.trim().toLowerCase().replace(/\s+/g, '_'); if (v) p.set(k, v);
            });
            preview.textContent = url + (url.includes('?') ? '&' : '?') + p.toString() + (hash ? '#' + hash : '');
        };
        f.addEventListener('input', update); update();
    }
    document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
        await navigator.clipboard?.writeText(b.dataset.copy); b.classList.add('copied'); setTimeout(() => b.classList.remove('copied'), 1500);
    }));
})();
</script>
@endpush
