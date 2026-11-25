@extends('layouts.app')
@section('title', __('ads.title'))
@section('content')
@php($zl = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ').' zł')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="zap" :size="26" /></span>
        <div><h1>{{ __('ads.title') }}</h1><p class="muted">{{ __('ads.subtitle') }}</p></div></div>
    <div class="dash-head-right">
        <details class="import-pop"><summary class="btn"><x-icon name="upload" :size="16" />{{ __('ads.import') }}</summary>
            <form method="post" action="{{ route('social.ads.import') }}" enctype="multipart/form-data" class="card stack">
                @csrf<p class="muted small">{{ __('ads.import_hint') }}</p>
                <input type="file" name="csv" accept=".csv,text/csv,.txt" required>
                <label class="field"><span>{{ __('ads.import_date') }}</span><input type="date" name="measured_on"></label>
                <button class="btn btn-dark">{{ __('ads.import') }}</button></form>
        </details>
        <a href="{{ route('social.ads.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('ads.new') }}</a>
    </div>
</div>
@if(session('unmatched'))<div class="flash flash-error" style="margin: 0 0 1rem">{{ __('ads.unmatched', ['names' => implode(', ', session('unmatched'))]) }}</div>@endif

<div class="kpis kpis-4">
    <div class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="grid" :size="24" /></span><div><span class="kpi-label">{{ __('ads.spend') }}</span><div class="kpi-value"><strong>{{ number_format($summary['spend'], 0, ',', ' ') }} zł</strong></div><small>{{ __('ads.of_budget', ['b' => number_format($summary['budget'], 0, ',', ' ')]) }}</small></div></div><x-waves variant="card" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="cursor" :size="24" /></span><div><span class="kpi-label">{{ __('ads.clicks') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($summary['clicks']) }}</strong></div><small class="muted">CPC {{ $zl($summary['cpc']) }} · CTR {{ $summary['ctr'] !== null ? number_format($summary['ctr'], 2, ',', ' ').'%' : '—' }}</small></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="check-circle" :size="24" /></span><div><span class="kpi-label">{{ __('ads.results') }}</span><div class="kpi-value"><strong>{{ $summary['conversions'] + $summary['leads'] }}</strong></div><small class="muted">{{ __('ads.cpa') }} {{ $zl($summary['cpa']) }}</small></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="trend-up" :size="24" /></span><div><span class="kpi-label">ROAS</span><div class="kpi-value"><strong>{{ $summary['roas'] !== null ? number_format($summary['roas'], 2, ',', ' ') : '—' }}</strong></div><small class="muted">{{ __('ads.revenue') }} {{ number_format($summary['revenue'], 0, ',', ' ') }} zł</small></div></div><x-waves variant="kpi" /></div>
</div>

<div class="chips" style="margin-bottom: 1rem">
    @foreach(['current', 'active', 'ending', 'finished', 'all'] as $f)
        <a href="{{ route('social.ads.index', array_filter(['filter' => $f, 'client' => request('client'), 'campaign' => request('campaign')])) }}" @class(['chip', 'chip-dark' => $filter === $f])>{{ __('ads.filter_'.$f) }}</a>
    @endforeach
    <form class="filters" style="margin: 0 0 0 auto">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <select name="client" onchange="this.form.submit()"><option value="">{{ __('ui.all_clients') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="campaign" onchange="this.form.submit()"><option value="">{{ __('inf.all_campaigns') }}</option>@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected(request('campaign') == $c->id)>{{ $c->name }}</option>@endforeach</select>
    </form>
</div>

<section class="card">
    <div class="table-scroll"><table class="table ads-table">
        <thead><tr><th>{{ __('ads.ad') }}</th><th>{{ __('ads.period') }}</th><th>{{ __('ads.budget_usage') }}</th><th>{{ __('ads.clicks') }}</th><th>CPC</th><th>{{ __('ads.results') }}</th><th>ROAS</th></tr></thead>
        <tbody>
        @forelse($ads as $a)
            @php($m = \App\Services\AdService::metrics($a->totals()))
            <tr>
                <td><a href="{{ route('social.ads.edit', $a) }}" class="ad-name"><span class="ad-platform ap-{{ $a->platform }}">{{ __('ads.platform_short_'.$a->platform) }}</span>
                    <span><strong>{{ $a->name }}</strong><small class="muted d-block">{{ $a->client->name }}@if($a->campaign) · {{ $a->campaign->name }}@endif @if($a->publication) · <x-icon name="share" :size="12" /> {{ __('ads.boosted_post') }}@endif</small></span></a></td>
                <td class="small"><span class="pill ad-{{ $a->status }}"><i></i>{{ __('ads.status_'.$a->status) }}</span>
                    <small class="d-block muted">{{ $a->starts_on?->format('d.m') }}–{{ $a->ends_on?->format('d.m') ?? '…' }}</small>
                    @if($a->endsSoon(3))<small class="d-block overdue">{{ __('ads.ends_soon') }}</small>@endif</td>
                <td style="min-width: 160px">@include('social.ads._budget', ['spent' => $m['spend'], 'budget' => (float) $a->budget])</td>
                <td>{{ \App\Support\Num::short($m['clicks']) }}@if($m['ctr'] !== null)<small class="muted d-block">CTR {{ number_format($m['ctr'], 2, ',', ' ') }}%</small>@endif</td>
                <td>{{ $zl($m['cpc']) }}</td>
                <td>{{ $m['conversions'] + $m['leads'] }}@if($m['cpa'] !== null)<small class="muted d-block">{{ $zl($m['cpa']) }} / {{ __('ads.result_short') }}</small>@endif</td>
                <td>{{ $m['roas'] !== null ? number_format($m['roas'], 2, ',', ' ') : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ __('ads.none') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
