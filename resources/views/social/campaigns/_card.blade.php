{{-- Karta kampanii na liście. $c, $i (CampaignInsights), $t (totals), $active (bool), $q (query builder) --}}
@php($cover = $i->cover())
@php($used = (float) $c->budget > 0 ? min(100, (int) round($t['spend'] / (float) $c->budget * 100)) : null)
<a href="{{ route('social.campaigns.index', $q(['c' => $c->id])) }}#detail" @class(['mk-card', 'on' => $active])>
    <span class="mk-card-img">@if($cover)<img src="{{ route('files.show', [$cover, $cover->name]) }}" alt="" loading="lazy">@else<x-icon name="layers" :size="26" />@endif
        <small class="mk-card-brand">{{ $c->client->name }}</small></span>
    <span class="mk-card-body">
        <span class="mk-card-icons">@foreach($i->platformKeys()->take(4) as $pk)<x-platform :platform="$pk" :size="18" />@endforeach</span>
        <strong>{{ $c->name }}@unless(\Illuminate\Support\Str::contains(mb_strtolower($c->name), mb_strtolower($c->client->name))) <span class="muted">– {{ $c->client->name }}</span>@endunless</strong>
        <span class="mk-status mk-{{ $c->status }}">{{ __('mkt.status_'.$c->status) }}</span>
        <small class="mk-card-meta"><x-icon name="calendar" :size="13" /> @if($c->starts_on){{ $c->starts_on->translatedFormat('j M') }}@if($c->ends_on) – {{ $c->ends_on->translatedFormat('j M') }}@endif @else — @endif
            <span>{{ number_format((float) $c->budget, 2, ',', ' ') }} zł</span></small>
        @php($g = $goals[$c->id])
        @php($pc = $pacing[$c->id])
        @if($g->pct !== null || ($pc && $pc->state !== 'ok'))<small class="mk-card-flags">@if($g->pct !== null)<span @class(['mk-goal-pct', 'done' => $g->pct >= 100])>{{ __('mkt.goal_short', ['n' => $g->pct]) }}</span>@endif
            @if($pc && $pc->state !== 'ok')<span class="mk-pace-flag mk-pace-{{ $pc->state }}" title="{{ __('mkt.pace_'.$pc->state) }}">⚠ {{ __('mkt.pace_short_'.$pc->state) }}</span>@endif</small>@endif
        @if($used !== null)<i class="mk-card-bar"><i style="width: {{ $used }}%"></i></i>@endif
    </span>
</a>
