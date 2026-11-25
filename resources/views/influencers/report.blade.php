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
                <dt>{{ __('inf.cost_promotion') }}</dt><dd>{{ $zl($costs['promotion']) }}</dd>
                <dt>{{ __('inf.cost_hours', ['h' => \App\Models\TimeEntry::hours($costs['minutes'])]) }}</dt><dd>{{ $zl($costs['hours']) }}</dd>
                <dt><strong>{{ __('inf.total_cost') }}</strong></dt><dd><strong>{{ $zl($costs['total']) }}</strong></dd>
            </dl>
        @else
            <p class="dark-title" style="color: var(--navy)">{{ $zl($costs['total']) }}</p>
            <p class="muted small">{{ __('inf.client_cost_hint') }}</p>
        @endif
    </section>
</div>
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
