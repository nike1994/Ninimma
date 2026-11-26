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
            <a href="{{ route('social.campaigns.create', array_filter(['client' => $clientId])) }}" class="qt"><span><x-icon name="layers" :size="20" /></span>{{ __('social.new_campaign') }}</a>
            <a href="{{ route('social.stats') }}" class="qt"><span><x-icon name="bar-chart" :size="20" /></span>{{ __('social.enter_stats') }} @if($statsQueue->count())<em>{{ $statsQueue->count() }}</em>@endif</a>
            <a href="{{ route('social.ads.index') }}" class="qt"><span><x-icon name="zap" :size="20" /></span>{{ __('ads.title_short') }} @if($adsEnding)<em>{{ $adsEnding }}</em>@endif</a>
            <a href="{{ route('licenses.index', ['filter' => $licConflicts ? 'conflicts' : 'expiring']) }}" class="qt"><span><x-icon name="shield" :size="20" /></span>{{ __('lic.nav') }} @if($licConflicts + $licExpiring)<em @class(['em-alert' => $licConflicts])>{{ $licConflicts + $licExpiring }}</em>@endif</a>
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
                            <a href="{{ route('social.contents.edit', $pub->content_id) }}" @class(['week-item', $pub->status, 'lic-flagged' => isset($licFlags[$pub->id])]) style="top: {{ $pos }}%" title="{{ $pub->scheduled_at->format('H:i') }} · {{ $pub->account->name }} · {{ $pub->content->title }}@isset($licFlags[$pub->id]) — {{ $licFlags[$pub->id]->implode(' ') }}@endisset">
                                @include('social._thumb', ['pub' => $pub])
                                @isset($licFlags[$pub->id])<span class="lic-flag" aria-label="{{ __('lic.calendar_flag') }}"><x-icon name="shield" :size="11" /></span>@endisset
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
        <div class="card-head"><h2>{{ __('social.campaigns') }}</h2>
            <span class="head-links"><a href="{{ route('social.ads.index') }}" class="link-arrow small">{{ __('ads.paid_spend', ['spend' => number_format($adSummary['spend'], 0, ',', ' ')]) }} <x-icon name="arrow-right" :size="15" /></a>
            <a href="{{ route('social.campaigns.create', array_filter(['client' => $clientId])) }}" class="btn btn-small btn-pill-round"><x-icon name="plus" :size="14" /> {{ __('social.new_campaign') }}</a></span></div>
        @forelse($campaigns as $cmp)
            @php($metric = $cmp->headlineMetric())
            <a href="{{ route('social.campaigns.edit', $cmp) }}" class="campaign-row">
                <x-avatar :name="$cmp->client->name" :size="40" />
                <span class="campaign-name"><strong>{{ $cmp->name }}</strong><span class="pill cmp-{{ $cmp->status }}"><i></i>{{ __('social.cmp_'.$cmp->status) }}</span></span>
                <span class="campaign-num"><small>{{ __('social.budget_short') }}</small><strong>{{ number_format((float) $cmp->budget, 0, ',', ' ') }} zł</strong>
                    @if($cmp->budgetUsedPct() !== null)<span @class(['mini-bar', 'warn' => $cmp->budgetUsedPct() >= 90])><i style="width: {{ min(100, $cmp->budgetUsedPct()) }}%"></i></span><small class="muted">{{ __('ads.used_pct', ['pct' => $cmp->budgetUsedPct()]) }}</small>@endif</span>
                <span class="campaign-num"><small>{{ $metric['label'] }}</small><strong>{{ $metric['value'] }}</strong></span>
            </a>
        @empty
            <p class="muted">{{ __('social.no_campaigns') }}</p>
        @endforelse
    </section>

    <div class="social-row-3">
        <section class="card">
            <div class="card-head"><h2>{{ __('social.todo') }}</h2>
                <button type="button" class="btn btn-small btn-pill-round" data-toggle="#task-form"><x-icon name="plus" :size="14" /> {{ __('social.add_task') }}</button></div>
            <form method="post" action="{{ route('social.tasks.store') }}" id="task-form" class="task-form" hidden>
                @csrf
                <input name="title" placeholder="{{ __('social.task_title') }}" required maxlength="200">
                <select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select>
                <input type="date" name="due_on">
                <select name="assigned_to"><option value="">—</option>@foreach($team as $m)<option value="{{ $m->id }}" @selected($m->id === auth()->id())>{{ $m->name }}</option>@endforeach</select>
                <button class="btn btn-dark btn-small">{{ __('ui.add') }}</button>
            </form>
            @if($statsQueue->isNotEmpty())
                <a href="{{ route('social.stats') }}" class="todo-row todo-auto">
                    <span class="todo-box auto"><x-icon name="bar-chart" :size="12" /></span>
                    <span class="todo-text">{{ trans_choice('social.stats_due', $statsQueue->count(), ['n' => $statsQueue->count()]) }}</span>
                </a>
            @endif
            @if($apiProblems->isNotEmpty())
                <a href="{{ route('social.contents.edit', $apiProblems->first()->content_id) }}" class="todo-row todo-auto">
                    <span class="todo-box auto lic"><x-icon name="zap" :size="12" /></span>
                    <span class="todo-text"><span class="overdue">{{ trans_choice('api.failed_count', $apiProblems->count(), ['n' => $apiProblems->count()]) }}</span></span>
                </a>
            @endif
            @if($licConflicts)
                <a href="{{ route('licenses.index', ['filter' => 'conflicts']) }}" class="todo-row todo-auto">
                    <span class="todo-box auto lic"><x-icon name="shield" :size="12" /></span>
                    <span class="todo-text"><span class="overdue">{{ trans_choice('lic.n_conflicts', $licConflicts, ['n' => $licConflicts]) }}</span> · {{ __('lic.kpi_conflicts_hint') }}</span>
                </a>
            @endif
            @if($awaitingClient->isNotEmpty())
                <a href="{{ route('social.contents.edit', $awaitingClient->first()->content_id) }}" class="todo-row todo-auto">
                    <span class="todo-box auto"><x-icon name="eye" :size="12" /></span>
                    <span class="todo-text">{{ trans_choice('social.awaiting_count', $awaitingClient->count(), ['n' => $awaitingClient->count()]) }}
                        @if($awaitingClient->where('client_status', 'changes')->count()) · <span class="overdue">{{ __('social.changes_requested') }}</span>@endif</span>
                </a>
            @endif
            @foreach($tasks as $t)
                <div @class(['todo-row', 'done' => $t->status === \App\Enums\TicketStatus::Done])>
                    <form method="post" action="{{ route('social.tasks.toggle', $t) }}">@csrf @method('patch')
                        <button class="todo-box" aria-label="{{ __('social.toggle_done') }}">@if($t->status === \App\Enums\TicketStatus::Done)<x-icon name="check" :size="13" />@endif</button></form>
                    <a href="{{ route('tickets.show', $t) }}" class="todo-text">{{ $t->title }} <small class="muted">· {{ $t->client->name }}</small></a>
                    @if($t->due_on)<span @class(['todo-date', 'overdue' => $t->isOverdue()])><x-icon name="calendar" :size="14" />{{ $t->due_on->translatedFormat('j M') }}</span>@endif
                    @if($t->assignee)<x-avatar :name="$t->assignee->name" :size="28" title="{{ $t->assignee->name }}" />@endif
                </div>
            @endforeach
            @if($tasks->isEmpty() && $statsQueue->isEmpty() && $awaitingClient->isEmpty() && ! $licConflicts && $apiProblems->isEmpty())<p class="muted">{{ __('ui.nothing_here') }}</p>@endif
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

        <section class="card card-notes">
            <h2>{{ __('social.notes') }}</h2>
            <ul class="notes">
                @foreach($notes as $n)
                    <li @class(['done' => $n->is_done])>
                        <form method="post" action="{{ route('social.notes.toggle', $n) }}">@csrf @method('patch')<button class="note-dot-btn" aria-label="{{ __('social.toggle_done') }}"></button></form>
                        <span>{{ $n->body }}</span>
                        <form method="post" action="{{ route('social.notes.destroy', $n) }}">@csrf @method('delete')<button class="note-del" aria-label="{{ __('social.remove') }}"><x-icon name="x" :size="14" /></button></form>
                    </li>
                @endforeach
            </ul>
            <form method="post" action="{{ route('social.notes.store') }}" class="note-add">
                @csrf<input name="body" placeholder="{{ __('social.add_note') }}" maxlength="300" required><button class="icon-btn icon-btn-sm" aria-label="{{ __('ui.add') }}"><x-icon name="plus" :size="16" /></button>
            </form>
            <x-waves variant="card" />
        </section>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/dashboard.js') }}?v=3"></script>
<script>document.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => { const t = document.querySelector(b.dataset.toggle); t.hidden = !t.hidden; t.querySelector('input')?.focus(); }));</script>
@endpush
