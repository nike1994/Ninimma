@extends('layouts.app')
@section('title', __('inf.title'))
@section('content')
@php($q = fn ($extra = []) => array_filter(array_merge(request()->only(['view', 'campaign', 'status', 'filter', 'q', 'tag']), $extra)))
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="users" :size="26" /></span>
        <div><h1>{{ __('inf.title') }}</h1><p class="muted">{{ __('inf.subtitle') }}</p></div></div>
    <div class="dash-head-right">
        <a href="{{ route('materials.index') }}" class="btn"><x-icon name="image" :size="16" />{{ __('inf.library') }}</a>
        <a href="{{ route('influencers.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('inf.new_person') }}</a>
    </div>
</div>
<div class="social-filters">
    <div class="segmented seg-light">
        @foreach(['grid' => 'grid', 'board' => 'layers', 'table' => 'list'] as $v => $icon)
            <a href="{{ route('influencers.index', $q(['view' => $v])) }}" @class(['active' => $view === $v])><x-icon :name="$icon" :size="15" /> {{ __('inf.view_'.$v) }}</a>
        @endforeach
    </div>
    <form class="filters" style="margin: 0">
        <input type="hidden" name="view" value="{{ $view }}">
        <label class="search-field"><x-icon name="search" :size="18" /><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('inf.search') }}"></label>
        @if($view !== 'grid')
            <select name="campaign" onchange="this.form.submit()"><option value="">{{ __('inf.all_campaigns') }}</option>
                @foreach($campaigns as $cmp)<option value="{{ $cmp->id }}" @selected(request('campaign') == $cmp->id)>{{ $cmp->name }}</option>@endforeach</select>
            <select name="status" onchange="this.form.submit()">
                @foreach(['active', 'done', 'cancelled', 'all'] as $st)<option value="{{ $st }}" @selected(request('status', 'active') === $st)>{{ __('inf.status_'.$st) }}</option>@endforeach</select>
        @endif
    </form>
</div>
@if($view === 'table')
    <div class="chips" style="margin-bottom: 1rem">
        @foreach(['' => 'inf.filter_all', 'not_delivered' => 'inf.filter_not_delivered', 'missing_materials' => 'inf.filter_missing', 'unpaid' => 'inf.filter_unpaid', 'license_expiring' => 'inf.filter_license'] as $f => $label)
            <a href="{{ route('influencers.index', $q(['filter' => $f ?: null])) }}" @class(['chip', 'chip-dark' => request('filter', '') === $f])>{{ __($label) }}</a>
        @endforeach
    </div>
@endif
@if($view === 'grid' && $tags->isNotEmpty())
    <div class="chips" style="margin-bottom: 1rem">
        <a href="{{ route('influencers.index') }}" @class(['chip', 'chip-dark' => ! request('tag')])>{{ __('inf.all_tags') }}</a>
        @foreach($tags as $t)<a href="{{ route('influencers.index', ['tag' => $t]) }}" @class(['chip', 'chip-dark' => request('tag') === $t])>#{{ $t }}</a>@endforeach
    </div>
@endif

@if($view === 'grid')
    <div class="inf-grid">
        @forelse($influencers as $inf)
            <a href="{{ route('influencers.show', $inf) }}" @class(['inf-card', 'inactive' => ! $inf->is_active])>
                <div class="inf-card-top">@include('influencers._photo', ['inf' => $inf, 'size' => 84])<x-waves variant="card" /></div>
                <strong>{{ $inf->name }}</strong>
                <span class="inf-platforms">@foreach($inf->profiles ?? [] as $p)@if(!empty($p['platform']))<x-platform :platform="$p['platform']" :size="20" />@endif @endforeach</span>
                <small class="muted">{{ \App\Support\Num::short($inf->followers()) }} {{ __('inf.followers') }}</small>
                @if($inf->rating)<span class="stars">{{ str_repeat('★', $inf->rating) }}<i>{{ str_repeat('★', 5 - $inf->rating) }}</i></span>@endif
                <span class="inf-tags">@foreach(array_slice($inf->tags ?? [], 0, 3) as $t)<span class="tag">#{{ $t }}</span>@endforeach</span>
                @php($active = $inf->collaborations->where('status', 'active')->count())
                @if($active)<span class="pill pill-with_agency">{{ trans_choice('inf.active_collabs', $active, ['n' => $active]) }}</span>@endif
            </a>
        @empty
            <p class="muted">{{ __('inf.none') }}</p>
        @endforelse
    </div>
@elseif($view === 'board')
    <div class="inf-board">
        @foreach(\App\Models\Collaboration::STAGES as $st)
            @php($col = $collabs->filter(fn ($c) => $c->stage() === $st))
            <div class="inf-col">
                <div class="board-head">{{ $loop->iteration }}. {{ __('inf.stage_'.$st) }} <span>{{ $col->count() }}</span></div>
                @foreach($col as $c)
                    <a href="{{ route('collaborations.show', $c) }}" class="inf-task">
                        @include('influencers._photo', ['inf' => $c->influencer, 'size' => 34])
                        <span><strong>{{ $c->influencer->name }}</strong><small class="muted">{{ $c->campaign->name }}</small></span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
@else
    <section class="card">
        <div class="table-scroll">
        <table class="table">
            <thead><tr><th>{{ __('inf.person') }}</th><th>{{ __('inf.campaign') }}</th><th>{{ __('inf.stage') }}</th><th>{{ __('inf.rate') }}</th><th>{{ __('inf.shipment') }}</th><th>{{ __('inf.materials') }}</th><th>{{ __('inf.license') }}</th></tr></thead>
            <tbody>
            @forelse($collabs as $c)
                <tr>
                    <td><a href="{{ route('collaborations.show', $c) }}" class="acc">@include('influencers._photo', ['inf' => $c->influencer, 'size' => 32])<strong>{{ $c->influencer->name }}</strong></a></td>
                    <td>{{ $c->campaign->name }}<small class="muted d-block">{{ $c->campaign->client->name }}</small></td>
                    <td>@include('influencers._stages')<small class="muted">{{ __('inf.stage_'.$c->stage()) }}</small></td>
                    <td>{{ number_format((float) $c->rate, 0, ',', ' ') }} zł<small class="muted d-block">{{ __('inf.billing_'.$c->billing) }}</small></td>
                    <td>@if(! $c->needs_shipment) — @elseif($c->shipmentsDelivered())<span class="pill pill-done">{{ __('inf.delivered') }}</span>@elseif($c->shipments->whereNotNull('sent_on')->count())<span class="pill pill-with_agency">{{ __('inf.in_transit') }}</span>@else<span class="pill">{{ __('inf.not_sent') }}</span>@endif</td>
                    <td>{{ $c->materials->where('is_received', true)->count() }} / {{ $c->materials->count() }}</td>
                    <td>@if($c->license_to)<span @class(['overdue' => $c->license_to->lte(today()->addDays(30))])>{{ $c->license_to->format('d.m.Y') }}</span>@else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('ui.nothing_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endif
@endsection
