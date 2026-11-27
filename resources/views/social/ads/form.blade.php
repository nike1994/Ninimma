@extends('layouts.app')
@section('title', $ad->exists ? $ad->name : __('ads.new'))
@section('content')
@php($zl = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ').' zł')
<div class="page-head">
    <div><h1 class="page-title" style="margin: 0">{{ $ad->exists ? $ad->name : __('ads.new') }}</h1>
        @if($ad->exists)<p class="muted">{{ __('ads.platform_'.$ad->platform) }} · {{ __('ads.obj_'.$ad->objective) }}@if($ad->publication) · <a href="{{ route('social.contents.edit', $ad->publication->content_id) }}">{{ __('ads.boosted_post') }}: {{ $ad->publication->content->title }}</a>@endif</p>@endif</div>
    @if($ad->exists)<span class="pill pill-lg ad-{{ $ad->status }}"><i></i>{{ __('ads.status_'.$ad->status) }}</span>@endif
</div>
@if(session('unmatched'))<div class="flash flash-error" style="margin: 0 0 1rem">{{ __('ads.unmatched', ['names' => implode(', ', session('unmatched'))]) }}</div>@endif

@if($ad->exists)
<div class="kpis kpis-4">
    <div class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="grid" :size="24" /></span><div style="flex: 1"><span class="kpi-label">{{ __('ads.spend') }}</span><div class="kpi-value"><strong>{{ number_format($metrics['spend'], 0, ',', ' ') }} zł</strong></div>@include('social.ads._budget', ['spent' => $metrics['spend'], 'budget' => (float) $ad->budget])</div></div></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="eye" :size="24" /></span><div><span class="kpi-label">{{ __('ads.impressions') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($metrics['impressions']) }}</strong></div><small class="muted">CPM {{ $zl($metrics['cpm']) }} · {{ __('social.kpi_reach') }} {{ \App\Support\Num::short($metrics['reach']) }}</small></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="cursor" :size="24" /></span><div><span class="kpi-label">{{ __('ads.clicks') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($metrics['clicks']) }}</strong></div><small class="muted">CPC {{ $zl($metrics['cpc']) }} · CTR {{ $metrics['ctr'] !== null ? number_format($metrics['ctr'], 2, ',', ' ').'%' : '—' }}</small></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="trend-up" :size="24" /></span><div><span class="kpi-label">{{ __('ads.results') }}</span><div class="kpi-value"><strong>{{ $metrics['conversions'] + $metrics['leads'] }}</strong></div><small class="muted">{{ __('ads.cpa') }} {{ $zl($metrics['cpa']) }} · ROAS {{ $metrics['roas'] !== null ? number_format($metrics['roas'], 2, ',', ' ') : '—' }}</small></div></div><x-waves variant="kpi" /></div>
</div>
@endif

<div class="grid-main">
    <div>
        @if($ad->exists)
        <section class="card">
            <h2><x-icon name="bar-chart" /> {{ __('ads.results_history') }}</h2>
            <p class="muted small">{{ __('ads.results_hint') }}</p>
            <form method="post" action="{{ route('social.ads.results.store', $ad) }}" class="ad-result-form">
                @csrf
                <label><span>{{ __('ui.date') }}</span><input type="date" name="measured_on" value="{{ today()->toDateString() }}" required></label>
                @php($last = $ad->results->last())
                @foreach(['spend' => 'ads.spend_pln', 'impressions' => 'ads.impressions', 'reach' => 'social.kpi_reach', 'clicks' => 'ads.clicks', 'landing_views' => 'ads.landing_views', 'add_to_cart' => 'ads.add_to_cart', 'conversions' => 'ads.conversions', 'leads' => 'ads.leads', 'revenue' => 'ads.revenue'] as $f => $label)
                    <label><span>{{ __($label) }}</span><input type="number" min="0" step="{{ in_array($f, ['spend', 'revenue']) ? '0.01' : '1' }}" name="{{ $f }}" placeholder="{{ $last?->{$f} }}" @if($f === 'spend') required @endif></label>
                @endforeach
                <button class="btn btn-dark"><x-icon name="plus" :size="14" /> {{ __('ads.add_result') }}</button>
            </form>
            <div class="table-scroll"><table class="table">
                <thead><tr><th>{{ __('ui.date') }}</th><th>{{ __('ads.spend') }}</th><th>{{ __('ads.impressions') }}</th><th>{{ __('ads.clicks') }}</th><th>{{ __('ads.conversions') }}</th><th>{{ __('ads.leads') }}</th><th>{{ __('ads.revenue') }}</th><th></th></tr></thead>
                <tbody>@forelse($ad->results->sortByDesc('measured_on') as $r)<tr>
                    <td>{{ $r->measured_on->format('d.m.Y') }}</td><td>{{ $zl($r->spend) }}</td><td>{{ number_format($r->impressions, 0, ',', ' ') }}</td><td>{{ $r->clicks }}</td><td>{{ $r->conversions }}</td><td>{{ $r->leads }}</td><td>{{ $zl($r->revenue) }}</td>
                    <td class="small muted">{{ match ($r->source) { 'import' => 'CSV', 'manual' => $r->user?->name, default => __('ads.source_api', ['p' => __('ads.platform_'.$r->source)]) } }}</td></tr>
                @empty<tr><td colspan="8" class="muted">{{ __('ads.no_results') }}</td></tr>@endforelse</tbody>
            </table></div>
            <form method="post" action="{{ route('social.ads.import.one', $ad) }}" enctype="multipart/form-data" class="inline-form small">
                @csrf<span class="muted">{{ __('ads.import_one_hint') }}</span><input type="file" name="csv" accept=".csv,text/csv,.txt" required><input type="date" name="measured_on" title="{{ __('ads.import_date') }}"><button class="btn btn-small">{{ __('ads.import') }}</button>
            </form>
        </section>
        @endif
    </div>
    <aside>
        <form method="post" action="{{ $ad->exists ? route('social.ads.update', $ad) : route('social.ads.store') }}" class="card stack">
            @csrf @if($ad->exists) @method('put') @endif
            <h2><x-icon name="sliders" /> {{ __('ads.settings') }}</h2>
            <x-field name="name" :label="__('ui.name')" :value="$ad->name" required />
            <x-field name="external_name" :label="__('ads.external_name')" :value="$ad->external_name" :placeholder="__('ads.external_hint')" />
            <x-field name="external_id" :label="__('ads.external_id')" :value="$ad->external_id" :placeholder="__('ads.external_id_hint')" inputmode="numeric" />
            @if($ad->sync_error)<p class="small" style="color: var(--red)">{{ __('ads.sync_error') }}: {{ $ad->sync_error }}</p>
            @elseif($ad->synced_at)<p class="muted small">{{ __('ads.synced_at', ['at' => $ad->synced_at->format('d.m.Y H:i')]) }}</p>@endif
            <div class="form-row form-row-2">
                <label class="field"><span>{{ __('ui.client') }}</span><select name="client_id" required data-client-select>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $ad->client_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('social.campaign') }}</span><select name="campaign_id" data-campaign-select><option value="">—</option>@foreach($campaigns as $c)<option value="{{ $c->id }}" data-client="{{ $c->client_id }}" @selected(old('campaign_id', $ad->campaign_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            </div>
            <div class="form-row form-row-2">
                <label class="field"><span>{{ __('social.platform') }}</span><select name="platform">@foreach(\App\Models\AdCampaign::PLATFORMS as $p)<option value="{{ $p }}" @selected(old('platform', $ad->platform) === $p)>{{ __('ads.platform_'.$p) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('ads.objective') }}</span><select name="objective">@foreach(\App\Models\AdCampaign::OBJECTIVES as $o)<option value="{{ $o }}" @selected(old('objective', $ad->objective) === $o)>{{ __('ads.obj_'.$o) }}</option>@endforeach</select></label>
            </div>
            <div class="form-row form-row-2">
                <x-field name="budget" type="number" step="0.01" min="0" :label="__('ads.budget_planned')" :value="$ad->budget" required />
                <label class="field"><span>{{ __('social.status') }}</span><select name="status">@foreach(\App\Models\AdCampaign::STATUSES as $st)<option value="{{ $st }}" @selected(old('status', $ad->status) === $st)>{{ __('ads.status_'.$st) }}</option>@endforeach</select></label>
            </div>
            <div class="form-row form-row-2">
                <x-field name="starts_on" type="date" :label="__('ads.promo_from')" :value="$ad->starts_on?->toDateString()" />
                <x-field name="ends_on" type="date" :label="__('ads.promo_to')" :value="$ad->ends_on?->toDateString()" />
            </div>
            <x-field name="target_url" type="url" :label="__('ads.target_url')" :value="$ad->target_url" :placeholder="__('ads.target_hint')" />
            @if($ad->campaign?->utmLinks?->isNotEmpty())
                <small class="muted">{{ __('ads.use_utm') }}</small>
                @foreach($ad->campaign->utmLinks->take(3) as $u)<code class="utm-pick" data-utm="{{ $u->full_url }}">{{ \Illuminate\Support\Str::limit($u->full_url, 60) }}</code>@endforeach
            @endif
            <div class="stack">
                <span class="field-label">{{ __('lic.material') }}</span>
                <small class="muted">{{ __('lic.material_hint') }}</small>
                @include('licenses._issues', ['issues' => $licenseIssues])
                <details class="mat-details" @if($ad->material_id || $licenseIssues->isNotEmpty()) open @endif>
                    <summary class="small">@if($ad->material)<span class="mat-summary"><x-material-thumb :material="$ad->material" :size="40" /> {{ $ad->material->description }}</span>@else{{ __('lic.no_material') }}@endif</summary>
                    @include('licenses._picker', ['name' => 'material_id', 'selected' => array_filter([old('material_id', $ad->material_id)]), 'multiple' => false])
                </details>
            </div>
            <x-field name="notes" type="textarea" rows="2" :label="__('site.notes')" :value="$ad->notes" />
            <button class="btn btn-dark">{{ __('ui.save') }}</button>
        </form>
        @if($ad->exists)
            <form method="post" action="{{ route('social.ads.destroy', $ad) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')" class="card">@csrf @method('delete')<button class="btn btn-small btn-block">{{ __('ads.delete') }}</button></form>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const client = document.querySelector('[data-client-select]');
    const filter = () => document.querySelectorAll('[data-campaign-select] option[data-client], [data-mat-picker] [data-client]').forEach(o => o.hidden = client.value && o.dataset.client !== client.value && !o.querySelector('input:checked'));
    client?.addEventListener('change', filter); filter();
    document.querySelectorAll('[data-utm]').forEach(c => c.addEventListener('click', () => { document.querySelector('input[name=target_url]').value = c.dataset.utm; }));
})();
</script>
@endpush
