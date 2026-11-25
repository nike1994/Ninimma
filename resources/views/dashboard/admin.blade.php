@extends('layouts.app')
@section('content')
<div class="dash-head">
    <div>
        <h1>{{ __('ui.dashboard') }}</h1>
        <p class="muted">{{ __('ui.dashboard_sub') }}</p>
    </div>
    <div class="dash-head-right">
        <form class="period">
            <x-icon name="calendar" :size="18" />
            <select name="period" onchange="this.form.submit()" aria-label="{{ __('ui.period') }}">
                @foreach(\App\Services\DashboardStats::PERIODS as $p)
                    <option value="{{ $p }}" @selected($period === $p)>{{ __('ui.period_'.$p) }}</option>
                @endforeach
            </select>
            <span class="period-range">{{ $stats->chartLabel() }}</span>
        </form>
        <a href="{{ route('admin.team') }}" class="avatar-stack" title="{{ __('ui.my_team') }}">
            @foreach($team->take(3) as $m)<x-avatar :name="$m->name" :size="38" />@endforeach
            @if($team->count() > 3)<span class="avatar-more">+{{ $team->count() - 3 }}</span>@endif
        </a>
    </div>
</div>

<div class="kpis">
    @foreach($cards as $c)
        <div class="kpi">
            <div class="kpi-top"><x-icon :name="$c['icon']" :size="22" /><span>{{ $c['label'] }}</span></div>
            <div class="kpi-value"><strong>{{ $c['value'] }}</strong>
                @if($c['trend'] !== null)
                    <span @class(['trend', 'trend-down' => $c['trend'] < 0])><x-icon :name="$c['trend'] < 0 ? 'trend-down' : 'trend-up'" :size="15" />{{ $c['trend'] > 0 ? '+' : '' }}{{ $c['trend'] }}%</span>
                @endif
            </div>
            <small class="muted">{{ $c['note'] }}</small>
            <x-waves variant="kpi" />
        </div>
    @endforeach
</div>

<div class="dash">
    <div class="dash-main">
        <div class="dash-row">
            <section class="card">
                <div class="card-head"><h2><x-icon name="message" /> {{ __('ui.active_conversations') }}</h2>
                    <a href="{{ route('tickets.index', ['status' => 'open']) }}" class="link-arrow small">{{ __('ui.all_conversations') }} <x-icon name="arrow-right" :size="15" /></a></div>
                @forelse($conversations as $m)
                    <a href="{{ route('tickets.show', $m->ticket).'#m'.$m->id }}" class="convo">
                        <x-avatar :name="$m->user->name" :size="42" />
                        <span class="convo-body">
                            <span class="convo-top"><strong>{{ $m->user->name }}</strong><span class="tag tag-{{ $m->ticket->type->value }}">{{ $m->ticket->client->name }}</span>
                                <time class="muted small">{{ $m->created_at->isToday() ? $m->created_at->format('H:i') : $m->created_at->format('d.m') }}</time></span>
                            <small class="muted">{{ \Illuminate\Support\Str::limit($m->body ?: '📎', 70) }}</small>
                        </span>
                        @if($m->unread)<span class="count-badge">{{ $m->unread }}</span>@endif
                    </a>
                @empty
                    <p class="muted">{{ __('ui.no_messages') }}</p>
                @endforelse
            </section>

            <section class="card">
                <div class="card-head"><h2><x-icon name="bar-chart" /> {{ __('ui.team_activity') }}</h2>
                    <span class="legend"><span><i class="m"></i>{{ __('ui.messages') }}</span><span><i class="a"></i>{{ __('ui.team_actions') }}</span></span></div>
                @include('dashboard._chart')
            </section>
        </div>

        <section class="card">
            <div class="card-head">
                <h2><x-icon name="check-square" /> {{ __('ui.task_board') }} <small class="muted">{{ trans_choice('ui.tasks_count', collect($board)->except('done')->flatten()->count(), ['n' => collect($board)->except('done')->flatten()->count()]) }}</small></h2>
                <a href="{{ route('tickets.index', ['status' => 'open']) }}" class="link-arrow small">{{ __('ui.all_tickets') }} <x-icon name="arrow-right" :size="15" /></a>
            </div>
            <div class="board">
                @foreach(['todo' => 'circle', 'doing' => 'loader', 'review' => 'layers', 'done' => 'check-circle'] as $col => $icon)
                    <div class="board-col col-{{ $col }}">
                        <div class="board-head"><x-icon :name="$icon" :size="18" />{{ __('ui.col_'.$col) }} <span>{{ $board[$col]->count() }}</span></div>
                        @forelse($board[$col]->take(4) as $t) @include('dashboard._task') @empty <p class="board-empty">{{ __('ui.nothing_here') }}</p> @endforelse
                        @if($board[$col]->count() > 4)<a href="{{ route('tickets.index', ['status' => $col === 'done' ? 'done' : 'open']) }}" class="board-more">+{{ $board[$col]->count() - 4 }}</a>@endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <aside class="dash-side">
        <section class="card card-flag">
            <x-waves variant="card" />
            <h2><x-icon name="flag" /> {{ __('ui.priorities') }}</h2>
            @forelse($priorities as $t)
                <a href="{{ route('tickets.show', $t) }}" class="prio-item">
                    <span class="prio-circle prio-{{ $t->priority->value }}"></span>
                    <span><strong>{{ $t->title }}</strong><small class="muted">{{ $t->client->name }} · @if($t->isOverdue())<span class="overdue">{{ __('ui.overdue_since', ['date' => $t->due_on->format('d.m')]) }}</span>@else{{ $t->priority->label() }}@endif</small></span>
                    <x-icon name="chevron-right" :size="16" />
                </a>
            @empty
                <p class="muted small">{{ __('ui.no_priorities') }}</p>
            @endforelse
        </section>

        @if($reminders->isNotEmpty() || $licenses->isNotEmpty() || $licConflicts || $statsQueueCount || $siteDeadlines->isNotEmpty() || $adsEnding->isNotEmpty())
        <section class="card">
            <h2><x-icon name="bell" /> {{ __('ui.today_reminders') }}</h2>
            @foreach($reminders as $r)
                <a href="{{ $r->url() ?? '#' }}" class="prio-item"><span class="prio-circle prio-high"></span>
                    <span><strong>{{ $r->body }}</strong><small class="muted">{{ $r->due_on->isToday() ? __('ui.today') : $r->due_on->format('d.m') }}@if($r->remindable instanceof \App\Models\Influencer) · {{ $r->remindable->name }}@elseif($r->remindable instanceof \App\Models\Collaboration) · {{ $r->remindable->influencer->name }}@endif</small></span></a>
            @endforeach
            @if($licConflicts)
                <a href="{{ route('licenses.index', ['filter' => 'conflicts']) }}" class="prio-item"><span class="prio-circle prio-urgent"></span>
                    <span><strong>{{ trans_choice('lic.n_conflicts', $licConflicts, ['n' => $licConflicts]) }}</strong><small class="muted">{{ __('lic.kpi_conflicts_hint') }}</small></span></a>
            @endif
            @foreach($licenses as $m)
                <a href="{{ route('licenses.index') }}#m{{ $m->id }}" class="prio-item prio-lic"><x-license-ring :material="$m" :size="38" />
                    <span><strong>{{ __('ui.license_ends', ['what' => $m->description]) }}</strong><small class="muted">{{ $m->collaboration->influencer->name }} · {{ $m->license_until->format('d.m') }}</small></span></a>
            @endforeach
            @foreach($adsEnding as $a)
                <a href="{{ route('social.ads.edit', $a) }}" class="prio-item"><span class="prio-circle prio-high"></span>
                    <span><strong>{{ __('ads.ends_on_reminder', ['name' => $a->name]) }}</strong><small class="muted">{{ $a->client->name }} · {{ $a->ends_on->format('d.m') }}</small></span></a>
            @endforeach
            @foreach($siteDeadlines as $d)
                <a href="{{ route('sites.show', $d->site) }}" class="prio-item"><span class="prio-circle {{ $d->daysLeft() <= 14 ? 'prio-urgent' : 'prio-high' }}"></span>
                    <span><strong>{{ __('site.kind_'.$d->kind) }}: {{ $d->name }}</strong><small class="muted">{{ $d->site->domain }} · {{ $d->due_on->format('d.m') }} · {{ trans_choice('site.days_left', $d->daysLeft(), ['n' => $d->daysLeft()]) }}</small></span></a>
            @endforeach
            @if($statsQueueCount)
                <a href="{{ route('social.stats') }}" class="prio-item"><span class="prio-circle"></span><span><strong>{{ trans_choice('social.stats_due', $statsQueueCount, ['n' => $statsQueueCount]) }}</strong></span></a>
            @endif
        </section>
        @endif

        <section class="card">
            <h2><x-icon name="calendar" /> {{ __('ui.upcoming_deadlines') }}</h2>
            @forelse($upcoming as $t)
                <a href="{{ route('tickets.show', $t) }}" class="deadline">
                    <span class="date-tile"><strong>{{ $t->due_on->format('j') }}</strong><small>{{ $t->due_on->translatedFormat('M') }}</small></span>
                    <span><strong>{{ $t->title }}</strong><small class="muted">{{ $t->client->name }} · #{{ $t->id }}</small></span>
                </a>
            @empty
                <p class="muted small">{{ __('ui.no_deadlines') }}</p>
            @endforelse
        </section>

        <section class="card">
            <h2><x-icon name="users" /> {{ __('ui.my_team') }}</h2>
            <div class="team">
                @foreach($team->take(6) as $m)<x-avatar :name="$m->name" :size="44" title="{{ $m->name }}" />@endforeach
                @if($team->count() > 6)<span class="avatar-more avatar-more-lg">+{{ $team->count() - 6 }}</span>@endif
                <a href="{{ route('admin.team') }}" class="team-add" title="{{ __('ui.invite_admin') }}"><x-icon name="plus" :size="18" /></a>
            </div>
        </section>

        <section class="card">
            <h2><x-icon name="zap" /> {{ __('ui.quick_actions') }}</h2>
            <div class="stack">
                <a href="{{ route('tickets.index', ['status' => 'waiting_client']) }}" class="btn btn-dark btn-block btn-lg"><x-icon name="message" :size="18" />{{ __('ui.awaiting_reply') }}</a>
                <a href="{{ route('tickets.create') }}" class="btn btn-send btn-block btn-lg"><x-icon name="plus" :size="18" />{{ __('ui.create_task') }}</a>
            </div>
            <a href="{{ route('admin.system') }}" @class(['sys-status', 'bad' => $recentErrors || ! $lastCron])>
                <span class="sys-dot"></span>{{ __('ui.last_cron') }}: {{ $lastCron ? \Illuminate\Support\Carbon::parse($lastCron)->diffForHumans() : __('ui.never') }}
                @if($recentErrors) · {{ trans_choice('ui.errors_count', $recentErrors, ['n' => $recentErrors]) }}@endif
            </a>
        </section>
    </aside>
</div>

@if($dueToday)
<div class="toast" data-toast="due-{{ $dueToday->id }}-{{ today()->toDateString() }}" hidden>
    <span class="toast-icon"><x-icon name="calendar" :size="24" /></span>
    <a href="{{ route('tickets.show', $dueToday) }}"><small>{{ __('ui.reminder') }}</small><strong>{{ __('ui.due_today') }}</strong><span>{{ $dueToday->title }} – {{ $dueToday->client->name }}</span></a>
    <button type="button" class="link" data-toast-close aria-label="×"><x-icon name="x" :size="18" /></button>
</div>
@endif
@endsection
@push('scripts')<script src="{{ asset('js/dashboard.js') }}?v=1"></script>@endpush
