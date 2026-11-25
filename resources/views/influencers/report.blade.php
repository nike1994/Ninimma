@extends('layouts.app')
@section('title', __('inf.report').' · '.$campaign->name)
@section('content')
@php($zl = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ').' zł')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="bar-chart" :size="26" /></span>
        <div><h1>{{ $campaign->name }}</h1><p class="muted">{{ $campaign->client->name }} · {{ $campaign->starts_on?->format('d.m') }}–{{ $campaign->ends_on?->format('d.m.Y') }} · {{ __('social.cmp_'.$campaign->status) }}</p></div></div>
    <div class="dash-head-right">
        <button onclick="window.print()" class="btn"><x-icon name="download" :size="16" />{{ __('inf.print') }}</button>
        @if($full)<a href="{{ route('social.campaigns.edit', $campaign) }}" class="btn">{{ __('ui.edit') }}</a>@endif
    </div>
</div>
@if($campaign->goal)<p class="card">{{ $campaign->goal }}</p>@endif
<div class="kpis kpis-4">
    <div class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="users" :size="24" /></span><div><span class="kpi-label">{{ __('social.kpi_reach') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($results['reach']) }}</strong></div></div></div><x-waves variant="card" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="heart" :size="24" /></span><div><span class="kpi-label">{{ __('social.kpi_engagement') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($results['engagement']) }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="cursor" :size="24" /></span><div><span class="kpi-label">{{ __('social.kpi_clicks') }}</span><div class="kpi-value"><strong>{{ \App\Support\Num::short($results['clicks']) }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="grid" :size="24" /></span><div><span class="kpi-label">{{ __('inf.total_cost') }}</span><div class="kpi-value"><strong>{{ number_format($costs['total'], 0, ',', ' ') }} zł</strong></div></div></div><x-waves variant="kpi" /></div>
</div>
<div class="grid-2">
    <section class="card">
        <h2>{{ __('inf.results') }}</h2>
        <dl class="dl">
            <dt>{{ __('inf.publications') }}</dt><dd>{{ $results['publications'] }}</dd>
            <dt>{{ __('inf.materials') }}</dt><dd>{{ $results['materials'] }}</dd>
            @if($results['sales'])<dt>{{ __('inf.sales_count') }}</dt><dd>{{ $results['sales'] }}</dd>@endif
            @if($results['revenue'])<dt>{{ __('inf.revenue') }}</dt><dd>{{ $zl($results['revenue']) }}</dd>@endif
            @if($summary['roas'])<dt>ROAS</dt><dd>{{ number_format($summary['roas'], 2, ',', ' ') }}</dd>@endif
            <dt>{{ __('inf.per_interaction') }}</dt><dd>{{ $zl($summary['per_interaction']) }}</dd>
            @if($full)<dt>{{ __('inf.per_material') }}</dt><dd>{{ $zl($summary['per_material']) }}</dd>@endif
        </dl>
    </section>
    <section class="card">
        <h2>{{ __('inf.costs') }}</h2>
        @if($full)
            <dl class="dl">
                <dt>{{ __('inf.cost_rates') }}</dt><dd>{{ $zl($costs['rates']) }}</dd>
                <dt>{{ __('inf.cost_products') }}</dt><dd>{{ $zl($costs['products']) }}</dd>
                <dt>{{ __('ads.cost_ads') }}</dt><dd>{{ $zl($costs['promotion']) }}</dd>
                <dt>{{ __('inf.cost_hours', ['h' => \App\Models\TimeEntry::hours($costs['minutes'])]) }}</dt><dd>{{ $zl($costs['hours']) }}</dd>
                <dt><strong>{{ __('inf.total_cost') }}</strong></dt><dd><strong>{{ $zl($costs['total']) }}</strong></dd>
            </dl>
        @else
            <p class="dark-title" style="color: var(--navy)">{{ $zl($costs['total']) }}</p>
            <p class="muted small">{{ __('inf.client_cost_hint') }}</p>
        @endif
    </section>
</div>
@if($paid['count'])
<div class="grid-2">
    <section class="card">
        <h2><x-icon name="zap" /> {{ __('ads.paid_results') }}</h2>
        <div class="metric-grid">
            <div><small>{{ __('ads.spend') }}</small><strong>{{ $zl($paid['spend']) }}</strong>@if($full && $paid['budget'])<small class="muted">{{ __('ads.of_budget', ['b' => number_format($paid['budget'], 0, ',', ' ')]) }}</small>@endif</div>
            <div><small>{{ __('ads.impressions') }}</small><strong>{{ \App\Support\Num::short($paid['impressions']) }}</strong></div>
            <div><small>{{ __('ads.clicks') }}</small><strong>{{ \App\Support\Num::short($paid['clicks']) }}</strong>@if($paid['ctr'] !== null)<small class="muted">CTR {{ number_format($paid['ctr'], 2, ',', ' ') }}%</small>@endif</div>
            <div><small>{{ __('ads.results') }}</small><strong>{{ $paid['conversions'] + $paid['leads'] }}</strong></div>
            <div><small>CPC</small><strong>{{ $zl($paid['cpc']) }}</strong></div>
            <div><small>CPM</small><strong>{{ $zl($paid['cpm']) }}</strong></div>
            <div><small>{{ __('ads.cpa') }}</small><strong>{{ $zl($paid['cpa']) }}</strong></div>
            <div><small>ROAS</small><strong>{{ $paid['roas'] !== null ? number_format($paid['roas'], 2, ',', ' ') : '—' }}</strong></div>
        </div>
    </section>
    <section class="card">
        <h2><x-icon name="heart" /> {{ __('ads.organic_results') }}</h2>
        <div class="metric-grid">
            <div><small>{{ __('social.kpi_reach') }}</small><strong>{{ \App\Support\Num::short($organic['reach']) }}</strong></div>
            <div><small>{{ __('social.kpi_engagement') }}</small><strong>{{ \App\Support\Num::short($organic['engagement']) }}</strong></div>
            <div><small>{{ __('social.kpi_clicks') }}</small><strong>{{ \App\Support\Num::short($organic['clicks']) }}</strong></div>
        </div>
        <p class="muted small">{{ __('ads.overlap_hint') }}</p>
    </section>
</div>
@if($full)
<section class="card">
    <h2>{{ __('ads.title') }}</h2>
    <div class="table-scroll"><table class="table">
        <thead><tr><th>{{ __('ads.ad') }}</th><th>{{ __('ads.spend') }}</th><th>{{ __('ads.clicks') }}</th><th>CPC</th><th>{{ __('ads.results') }}</th><th>{{ __('ads.cpa') }}</th><th>ROAS</th></tr></thead>
        <tbody>@foreach($ads as $a)@php($m = \App\Services\AdService::metrics($a->totals()))<tr>
            <td><a href="{{ route('social.ads.edit', $a) }}">{{ $a->name }}</a><small class="muted d-block">{{ __('ads.platform_'.$a->platform) }}</small></td>
            <td>{{ $zl($m['spend']) }}</td><td>{{ $m['clicks'] }}</td><td>{{ $zl($m['cpc']) }}</td><td>{{ $m['conversions'] + $m['leads'] }}</td><td>{{ $zl($m['cpa']) }}</td><td>{{ $m['roas'] !== null ? number_format($m['roas'], 2, ',', ' ') : '—' }}</td>
        </tr>@endforeach</tbody>
    </table></div>
</section>
@endif
@endif
@if($full && $rows->isNotEmpty())
<section class="card">
    <h2>{{ __('inf.compare_influencers') }}</h2>
    <div class="table-scroll"><table class="table">
        <thead><tr><th>{{ __('inf.person') }}</th><th>{{ __('inf.cost') }}</th><th>{{ __('inf.materials') }}</th><th>{{ __('social.kpi_reach') }}</th><th>{{ __('social.kpi_engagement') }}</th><th>{{ __('inf.cpe') }}</th><th>{{ __('inf.sales_count') }}</th></tr></thead>
        <tbody>@foreach($rows as $r)<tr>
            <td><a href="{{ route('collaborations.show', $r->collab) }}" class="acc">@include('influencers._photo', ['inf' => $r->collab->influencer, 'size' => 30])<strong>{{ $r->collab->influencer->name }}</strong></a></td>
            <td>{{ $zl($r->cost) }}</td><td>{{ $r->materials }}</td><td>{{ \App\Support\Num::short($r->reach) }}</td><td>{{ \App\Support\Num::short($r->engagement) }}</td><td>{{ $zl($r->cpe) }}</td><td>{{ $r->sales ?: '—' }}</td>
        </tr>@endforeach</tbody>
    </table></div>
</section>
@endif
@if($formats->isNotEmpty())
<section class="card">
    <h2>{{ __('inf.compare_formats') }}</h2>
    <table class="table"><thead><tr><th>{{ __('social.format') }}</th><th>{{ __('social.posts') }}</th><th>{{ __('social.kpi_reach') }}</th><th>{{ __('social.kpi_engagement') }}</th></tr></thead>
        <tbody>@foreach($formats as $f)<tr><td>{{ __('social.format_'.$f->format) }}</td><td>{{ $f->count }}</td><td>{{ \App\Support\Num::short($f->reach) }}</td><td>{{ \App\Support\Num::short($f->engagement) }}</td></tr>@endforeach</tbody></table>
</section>
@endif
@endsection
