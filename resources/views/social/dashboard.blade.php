@extends('layouts.app')
@section('title', __('social.title'))
@section('content')
@php($q = fn ($extra = []) => array_filter(array_merge(['period' => $period, 'client' => $clientId], $extra)))
<div class="dash-head">
    <div class="title-with-icon">
        <span class="title-icon"><x-icon name="megaphone" :size="26" /></span>
        <div><h1>{{ __('social.title') }}</h1><p class="muted">{{ __('social.subtitle') }}</p></div>
    </div>
</div>

<div class="social-filters">
    <form class="period">
        <x-icon name="calendar" :size="18" />
        @if($clientId)<input type="hidden" name="client" value="{{ $clientId }}">@endif
        <select name="period" onchange="this.form.submit()" aria-label="{{ __('ui.period') }}">
            @foreach(\App\Services\SocialStats::PERIODS as $p)<option value="{{ $p }}" @selected($period === $p)>{{ __('ui.period_'.$p) }}</option>@endforeach
        </select>
        <span class="period-range">{{ $stats->from->translatedFormat('j M') }} – {{ $stats->to->translatedFormat('j M Y') }}</span>
    </form>
    <div class="chips">
        <a href="{{ route('social.dashboard', $q(['client' => null])) }}" @class(['chip', 'chip-dark' => ! $clientId])>{{ __('social.all_accounts') }} <span>{{ $accountsCount }}</span></a>
        @foreach($brands as $b)
            <a href="{{ route('social.dashboard', $q(['client' => $b->client->id])) }}" @class(['chip', 'chip-dark' => $clientId === $b->client->id])>
                <x-avatar :name="$b->client->name" :size="24" />{{ $b->client->name }} <span>{{ $b->accounts->count() }}</span></a>
        @endforeach
        <a href="{{ route('social.accounts') }}" class="chip chip-add"><x-icon name="plus" :size="15" />{{ __('social.add_account') }}</a>
    </div>
</div>

<div class="kpis kpis-4">
    @foreach($kpis as $i => $k)
        <div @class(['kpi', 'kpi-dark' => $i === 0])>
            <div class="kpi-row">
                <span class="kpi-icon"><x-icon :name="$k['icon']" :size="24" /></span>
                <div>
                    <span class="kpi-label">{{ $k['label'] }}</span>
                    <div class="kpi-value"><strong>{{ \App\Support\Num::short($k['value']) }}</strong>
                        @if($k['trend'] !== null)<span @class(['trend-pill', 'down' => $k['trend'] < 0])><x-icon :name="$k['trend'] < 0 ? 'trend-down' : 'trend-up'" :size="13" />{{ $k['trend'] > 0 ? '+' : '' }}{{ $k['trend'] }}%</span>@endif
                    </div>
                </div>
            </div>
            @if($i > 0)<x-waves variant="kpi" />@else<x-waves variant="card" />@endif
        </div>
    @endforeach
</div>

<div class="social-grid">
    <section class="card funnel-card">
        <h2>{{ __('social.funnel') }}</h2>
        <div class="funnel">
            @foreach(['idea' => ['pencil', route('social.contents.index', ['stage' => 'idea'])], 'preparing' => ['file-text', route('social.contents.index', ['stage' => 'preparing'])], 'scheduled' => ['calendar', route('social.calendar')], 'published' => ['check-circle', route('social.calendar')]] as $key => [$icon, $href])
                <a href="{{ $href }}" class="funnel-step step-{{ $key }}"><x-icon :name="$icon" :size="22" /><span>{{ __('social.stage_'.$key) }}</span><strong>{{ $funnel[$key] }}</strong></a>
                @unless($loop->last)<x-icon name="arrow-right" :size="18" class="funnel-arrow" />@endunless
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>{{ __('ui.quick_actions') }}</h2>
        <div class="quick-tiles">
            <a href="{{ route('social.contents.create', ['stage' => 'preparing']) }}" class="qt qt-green"><span><x-icon name="plus" :size="22" /></span>{{ __('social.new_post') }}</a>
            <a href="{{ route('social.contents.create', ['stage' => 'idea']) }}" class="qt"><span><x-icon name="pencil" :size="20" /></span>{{ __('social.new_idea') }}</a>
            <a href="{{ route('social.stats') }}" class="qt"><span><x-icon name="bar-chart" :size="20" /></span>{{ __('social.enter_stats') }} @if($statsQueue->count())<em>{{ $statsQueue->count() }}</em>@endif</a>
            <a href="{{ route('social.calendar') }}" class="qt"><span><x-icon name="calendar" :size="20" /></span>{{ __('social.calendar') }}</a>
        </div>
    </section>

    <section class="card week-card">
        <div class="card-head">
            <h2>{{ __('social.calendar') }}</h2>
            <div class="week-nav">
                <a href="{{ route('social.dashboard', $q(['week' => $weekStart->subWeek()->toDateString()])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="16" /></a>
                <span>{{ __('social.week') }} {{ $weekStart->translatedFormat('j') }} – {{ $weekStart->addDays(6)->translatedFormat('j M Y') }}</span>
                <a href="{{ route('social.dashboard', $q(['week' => $weekStart->addWeek()->toDateString()])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-right" :size="16" /></a>
            </div>
        </div>
        @php($hours = range(8, 20, 2))
        <div class="week">
            <div class="week-hours"><span></span>@foreach($hours as $h)<span>{{ sprintf('%02d:00', $h) }}</span>@endforeach</div>
            @for($d = 0; $d < 7; $d++)
                @php($day = $weekStart->addDays($d))
                <div @class(['week-day', 'today' => $day->isToday()])>
                    <div class="week-day-head"><strong>{{ $day->translatedFormat('D') }}</strong><small>{{ $day->translatedFormat('j M') }}</small></div>
                    <div class="week-slots">
                        @foreach($week->filter(fn ($p) => $p->scheduled_at->isSameDay($day)) as $pub)
                            @php($pos = max(0, min(100, (($pub->scheduled_at->hour + $pub->scheduled_at->minute / 60) - 8) / 13 * 100)))
                            <a href="{{ route('social.contents.edit', $pub->content_id) }}" class="week-item {{ $pub->status }}" style="top: {{ $pos }}%" title="{{ $pub->scheduled_at->format('H:i') }} · {{ $pub->account->name }} · {{ $pub->content->title }}">
                                @include('social._thumb', ['pub' => $pub])
                                <i class="status-bar"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endfor
        </div>
    </section>

    <section class="card">
        <h2>{{ __('social.by_brand') }}</h2>
        <table class="table table-accounts">
            <thead><tr><th></th><th>{{ __('social.kpi_reach') }}</th><th>{{ __('social.engagement_short') }}</th><th>{{ __('social.posts') }}</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><div class="acc"><x-avatar :name="$row->client->name" :size="40" />
                        <span><strong>{{ $row->client->name }}</strong>
                            <span class="acc-platforms">@foreach($row->accounts as $a)<x-platform :platform="$a->platform" :size="18" title="{{ $a->platform->label() }}{{ $a->handle ? ' · @'.ltrim($a->handle, '@') : '' }}" />@endforeach</span></span></div></td>
                    <td><strong>{{ \App\Support\Num::short($row->reach) }}</strong>@if($row->reach_trend !== null)<span @class(['trend-pill sm', 'down' => $row->reach_trend < 0])>{{ $row->reach_trend > 0 ? '↑ +' : '↓ ' }}{{ $row->reach_trend }}%</span>@endif</td>
                    <td><strong>{{ \App\Support\Num::short($row->engagement) }}</strong>@if($row->engagement_trend !== null)<span @class(['trend-pill sm', 'down' => $row->engagement_trend < 0])>{{ $row->engagement_trend > 0 ? '↑ +' : '↓ ' }}{{ $row->engagement_trend }}%</span>@endif</td>
                    <td><strong>{{ $row->posts }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('social.no_accounts') }} <a href="{{ route('social.accounts') }}">{{ __('social.add_account') }}</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <div class="card-head"><h2>{{ __('social.engagement_trend') }}</h2>
            <span class="legend">@foreach($trend['lines'] as $l)<span><i style="background: {{ $l['color'] }}"></i>{{ $l['name'] }}</span>@endforeach</span></div>
        @include('social._trend')
    </section>

    <section class="card">
        <h2>{{ __('social.by_platform') }}</h2>
        <div class="platform-split">
            @include('social._donut')
            <ul class="platform-list">
                @forelse($platforms as $p)
                    <li><x-platform :platform="$p->platform" :size="24" /><span>{{ $p->platform->label() }}</span><strong>{{ $p->share }}%</strong></li>
                @empty
                    <li class="muted">{{ __('social.no_data') }}</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>{{ __('social.todo') }}</h2>
            <a href="{{ route('social.contents.create', ['stage' => 'preparing']) }}" class="btn btn-small"><x-icon name="plus" :size="14" /> {{ __('social.new_post') }}</a></div>
        @foreach($statsQueue as $pub)
            <a href="{{ route('social.stats') }}" class="todo-row">
                <span class="todo-box"></span>
                <span class="todo-text">{{ __('social.enter_stats_for', ['title' => $pub->content->title]) }} <small class="muted">({{ __('social.after_days', ['n' => \App\Models\Publication::CHECK_DAYS[$pub->checks_done]]) }})</small></span>
                <x-platform :platform="$pub->platform" :size="20" />
                <span class="todo-date"><x-icon name="calendar" :size="15" />{{ $pub->nextCheckAt()->translatedFormat('j M') }}</span>
            </a>
        @endforeach
        @foreach($preparing as $c)
            <a href="{{ route('social.contents.edit', $c) }}" class="todo-row">
                <span class="todo-box"></span>
                <span class="todo-text">{{ __('social.prepare', ['title' => $c->title]) }} <small class="muted">· {{ $c->client->name }}</small></span>
                <span class="pill stage-{{ $c->stage }}">{{ __('social.stage_'.$c->stage) }}</span>
            </a>
        @endforeach
        @if($statsQueue->isEmpty() && $preparing->isEmpty())<p class="muted">{{ __('ui.nothing_here') }}</p>@endif
    </section>

    <section class="card card-notes">
        <h2>{{ __('social.awaiting_client') }}</h2>
        @forelse($awaitingClient as $pub)
            <a href="{{ route('social.contents.edit', $pub->content_id) }}" class="note-row">
                <span @class(['note-dot', 'changes' => $pub->client_status === 'changes'])></span>
                <span><strong>{{ $pub->content->title }}</strong>
                    <small class="muted">{{ $pub->account->name }} · {{ $pub->scheduled_at->translatedFormat('j M, H:i') }}
                        @if($pub->client_status === 'changes') · <span class="overdue">{{ __('social.changes_requested') }}</span>@endif</small></span>
            </a>
        @empty
            <p class="muted">{{ __('social.nothing_awaiting') }}</p>
        @endforelse
        <x-waves variant="card" />
    </section>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/dashboard.js') }}?v=2"></script>@endpush
