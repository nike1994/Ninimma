@extends('layouts.app')
@section('title', __('mkt.client_title'))
@section('content')
@php
    $link = fn ($c) => route('reports.index', array_filter(['client' => $preview ? $client->id : null, 'c' => $c->id]));
    $fmt = fn ($v, $f) => $v === null ? '—' : match ($f) {
        'money' => $v >= 100000 ? \App\Support\Num::short((int) $v).' zł' : number_format((float) $v, $v >= 1000 ? 0 : 2, ',', ' ').' zł',
        'pct' => str_replace('.', ',', (string) round($v, 2)).'%',
        'x' => str_replace('.', ',', (string) $v).' ×',
        'freq' => str_replace('.', ',', (string) round($v, 1)),
        default => $v >= 100000 ? \App\Support\Num::short((int) $v) : number_format((float) $v, 0, ',', ' '),
    };
    $pct = fn ($v) => str_replace('.', ',', $v > 0 && $v < 1 ? number_format($v, 2) : (string) round($v, 1));
    $short = fn ($v) => \App\Support\Num::short((int) $v);
@endphp
@if($preview)
    <p class="mk-preview-bar"><x-icon name="eye" :size="16" /> {{ __('mkt.preview_as', ['client' => $client->name]) }}
        <a href="{{ route('social.campaigns.index', ['client' => $client->id, 'c' => $selected?->id]) }}" class="btn btn-small">{{ __('mkt.preview_back') }}</a></p>
@endif
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="megaphone" :size="26" /></span>
        <div><h1>{{ __('mkt.client_title') }}</h1><p class="muted">{{ __('mkt.client_subtitle') }}</p></div></div>
</div>

@if($campaigns->isEmpty())
    <section class="card"><p class="muted">{{ __('mkt.client_none') }}</p></section>
@else
<div class="mk-layout">
    <div class="mk-list">
        @foreach($campaigns as $c)
            @php($g = $goals[$c->id])
            @php($cover = $insights[$c->id]->cover())
            <a href="{{ $link($c) }}#detail" @class(['mk-card', 'on' => $selected->id === $c->id])>
                <span class="mk-card-img">@if($cover && auth()->user()->can('view', $cover))<img src="{{ route('files.show', [$cover, $cover->name]) }}" alt="" loading="lazy">@else<x-icon name="layers" :size="26" />@endif</span>
                <span class="mk-card-body">
                    <span class="mk-card-icons">@foreach($insights[$c->id]->platformKeys()->take(4) as $pk)<x-platform :platform="$pk" :size="18" />@endforeach</span>
                    <strong>{{ $c->name }}</strong>
                    <span class="mk-status mk-{{ $c->status }}">{{ __('mkt.status_'.$c->status) }}</span>
                    <small class="mk-card-meta"><x-icon name="calendar" :size="13" /> @if($c->starts_on){{ $c->starts_on->translatedFormat('j M') }}@if($c->ends_on) – {{ $c->ends_on->translatedFormat('j M') }}@endif @else — @endif
                        <span>{{ $fmt($g->value, $g->format) }}</span></small>
                    @if($g->pct !== null)<small class="mk-card-flags"><span @class(['mk-goal-pct', 'done' => $g->pct >= 100])>{{ __('mkt.goal_short', ['n' => $g->pct]) }}</span></small>
                        <i class="mk-card-bar"><i style="width: {{ min(100, $g->pct) }}%"></i></i>@endif
                </span>
            </a>
        @endforeach
    </div>

    @php($t = $totals[$selected->id])
    @php($g = $goals[$selected->id])
    @php($tr = $sel->trends())
    <section class="card mk-detail" id="detail">
        <div class="mk-detail-head">
            <span class="mk-card-icons">@foreach($sel->platformKeys() as $pk)<x-platform :platform="$pk" :size="22" />@endforeach</span>
            <h2>{{ $selected->name }}</h2>
            <span class="mk-status mk-{{ $selected->status }}">{{ __('mkt.status_'.$selected->status) }}</span>
            <span class="mk-spacer"></span>
            <a href="{{ route('reports.campaign', $selected) }}" class="btn btn-small"><x-icon name="download" :size="14" /> {{ __('mkt.full_report') }}</a>
        </div>
        <div class="mk-sum">
            <div class="mk-box mk-client-goal">
                <h3><x-icon name="flag" :size="18" /> {{ __('mkt.goal') }}</h3>
                <div class="mk-goalbox">
                    <span class="mk-obj">{{ __('mkt.obj_'.$g->objective) }}</span>
                    <div class="mk-goal-num"><strong>{{ $fmt($g->value, $g->format) }}</strong><small class="muted d-block">@if($g->target){{ __('mkt.goal_of', ['target' => $fmt($g->target, $g->format)]) }} · @endif{{ mb_strtolower(__('mkt.m_'.$g->metric)) }}</small></div>
                    @if($g->pct !== null)<i class="mk-bar"><i style="width: {{ min(100, $g->pct) }}%" @class(['done' => $g->pct >= 100])></i></i><small @class(['mk-goal-pct', 'done' => $g->pct >= 100])>{{ __('mkt.goal_pct', ['n' => $g->pct]) }}</small>@endif
                </div>
                @if($selected->goal)<p class="mk-goal"><x-icon name="check-circle" :size="18" /> {{ $selected->goal }}</p>@endif
                <p class="mk-line muted small"><x-icon name="calendar" :size="14" /> @if($selected->starts_on){{ $selected->starts_on->translatedFormat('j M') }} – {{ $selected->ends_on?->translatedFormat('j M Y') ?? '…' }}@else — @endif</p>
            </div>
            <div class="mk-results">
                <h3>{{ __('mkt.results') }}</h3>
                <div class="mk-metrics">
                    @foreach($sel->metrics($t) as $mt)
                        <div><small>{{ __('mkt.m_'.$mt->key) }}@if(\Illuminate\Support\Facades\Lang::has('mkt.m_'.$mt->key.'_hint')) <span class="mk-info" title="{{ __('mkt.m_'.$mt->key.'_hint') }}">ⓘ</span>@endif</small>
                            <strong>{{ $fmt($mt->value, $mt->format) }}</strong></div>
                    @endforeach
                </div>
                <div class="mk-chart-head"><h3>{{ __('mkt.over_time') }}</h3><span class="mk-legend"><i class="r"></i>{{ __('mkt.reach') }} <i class="c"></i>{{ __('mkt.clicks') }}</span></div>
                @include('social.campaigns._chart', ['s' => $sel->series()])
            </div>
        </div>
        @if($selected->retro_shared && $selected->hasRetro())
            <div class="mk-retro">
                <h3><x-icon name="check-square" :size="18" /> {{ __('mkt.retro') }}</h3>
                <div class="mk-retro-cols">
                    @foreach(['worked' => 'check-circle', 'failed' => 'x', 'next' => 'arrow-right'] as $k => $ic)
                        @if($selected->{'retro_'.$k})<div class="mk-retro-col mk-retro-{{ $k }}"><strong><x-icon :name="$ic" :size="15" /> {{ __('mkt.retro_'.$k) }}</strong><p>{!! nl2br(e($selected->{'retro_'.$k})) !!}</p></div>@endif
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>

@php($aud = $sel->audience())
@php($fun = $sel->funnel($t))
@php($creatives = $sel->creatives(3))
@php($hasDemo = $aud['gender'] || $aud['age'])
@php($hasLoc = $aud['city'] || $aud['country'])
@php($cols = ($fun->isNotEmpty() ? 1 : 0) + ($hasDemo ? 1 : 0) + ($creatives->isNotEmpty() ? 1 : 0))
@if($cols)
<div class="mk-row3 mk-row3-{{ $cols }}">
    @if($fun->isNotEmpty())
    <section class="card">
        <h2><x-icon name="filter" /> {{ __('mkt.funnel') }}</h2>
        <div class="mk-funnel">
            <div class="mk-funnel-steps">@foreach($fun as $i => $st)
                <div class="mk-step s{{ $i }}" style="--w: {{ 100 - $i * 12 }}%"><strong>{{ __('mkt.step_'.$st->key) }}</strong><span>{{ $short($st->value) }} <small>({{ $pct($st->of_total) }}%)</small></span></div>
            @endforeach</div>
            <div class="mk-funnel-rates"><small class="muted">{{ __('mkt.conv_rate') }}</small>@foreach($fun->skip(1) as $st)<span>↓ {{ str_replace('.', ',', $st->step_rate) }}%</span>@endforeach</div>
        </div>
    </section>
    @endif
    @if($hasDemo)
    <section class="card">
        <h2><x-icon name="users" /> {{ __('mkt.demography') }}</h2>
        <div class="mk-gender">
            <div><span class="mk-g f">♀</span><span><small>{{ __('mkt.women') }}</small><strong>{{ round($aud['gender']['female'] ?? 0) }}%</strong></span></div>
            <div><span class="mk-g m">♂</span><span><small>{{ __('mkt.men') }}</small><strong>{{ round($aud['gender']['male'] ?? 0) }}%</strong></span></div>
        </div>
        <h3 class="small">{{ __('mkt.age_of') }}</h3>
        @php($maxAge = max([1, ...array_values($aud['age'])]))
        @foreach($aud['age'] as $label => $v)
            <div class="mk-age"><span>{{ $label }}</span><i><i style="width: {{ $v / $maxAge * 100 }}%"></i></i><b>{{ round($v) }}%</b></div>
        @endforeach
    </section>
    @endif
    @if($creatives->isNotEmpty())
    <section class="card">
        <h2><x-icon name="zap" /> {{ __('mkt.best_creatives') }}</h2>
        @foreach($creatives as $cr)
            <div class="mk-creative">@if($cr->thumb && auth()->user()->can('view', $cr->thumb))<img src="{{ route('files.show', [$cr->thumb, $cr->thumb->name]) }}" alt="">@else<span class="mk-creative-empty"><x-icon name="image" :size="20" /></span>@endif
                <span><strong>{{ $cr->name }}</strong><small>CTR: <b>{{ str_replace('.', ',', $cr->ctr) }}%</b></small><small>{{ __('mkt.clicks') }}: <b>{{ number_format($cr->clicks, 0, ',', ' ') }}</b></small></span></div>
        @endforeach
    </section>
    @endif
</div>
@endif

@if($hasLoc)
<div class="mk-row2 mk-row2-1">
    <section class="card mk-loc">
        <h2><x-icon name="flag" /> {{ __('mkt.locations') }}</h2>
        <div class="mk-loc-body">
            <div>
                <div class="pv-modes mk-loc-tabs" role="tablist"><button type="button" class="on" data-loc="city">{{ __('mkt.top_cities') }}</button><button type="button" data-loc="country">{{ __('mkt.top_countries') }}</button></div>
                @foreach(['city', 'country'] as $dim)
                    <table class="mk-loc-list" data-loc-list="{{ $dim }}" @if($dim === 'country') hidden @endif>
                        @forelse(array_slice($aud[$dim], 0, 6, true) as $label => $v)<tr><td>{{ $label }}</td><td>{{ str_replace('.', ',', $v) }}%</td></tr>
                        @empty<tr><td class="muted small">{{ __('mkt.audience_empty') }}</td></tr>@endforelse
                    </table>
                @endforeach
            </div>
            @include('social.campaigns._map', ['cities' => $aud['city']])
        </div>
    </section>
</div>
@endif
@endif
<p><a href="{{ route('billing.index') }}" class="link-arrow">{{ __('ui.billing') }} <x-icon name="arrow-right" :size="16" /></a></p>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-loc]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-loc]').forEach(x => x.classList.toggle('on', x === b));
    document.querySelectorAll('[data-loc-list]').forEach(t => t.hidden = t.dataset.locList !== b.dataset.loc);
}));
</script>
@endpush
