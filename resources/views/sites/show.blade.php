@extends('layouts.app')
@section('title', $site->domain)
@section('content')
<header class="client-head">
    <span class="site-icon site-icon-lg platform-{{ $site->platform }}"><x-icon name="code" :size="36" /></span>
    <div class="client-id">
        <h1>{{ $site->domain }} @unless($site->is_active)<span class="pill">{{ __('ui.inactive') }}</span>@endunless</h1>
        <p class="muted"><a href="{{ route('admin.clients.show', $site->client) }}">{{ $site->client->name }}</a> · {{ __('site.platform_'.$site->platform) }}@if($site->hosting) · {{ $site->hosting }}@endif</p>
    </div>
    <div class="client-badges">
        <a href="https://{{ $site->domain }}" target="_blank" rel="noopener" class="btn btn-small"><x-icon name="link" :size="15" /> {{ __('site.open_site') }}</a>
        <a href="{{ route('sites.edit', $site) }}" class="btn btn-small"><x-icon name="edit" :size="15" /> {{ __('ui.edit') }}</a>
    </div>
</header>
<nav class="tabs">
    @foreach(['overview' => 'site.tab_overview', 'reviews' => 'site.tab_reviews', 'seo' => 'site.tab_seo', 'report' => 'site.tab_report'] as $t => $label)
        <a href="{{ route('sites.show', [$site, 'tab' => $t]) }}" @class(['tab', 'active' => $tab === $t])>{{ __($label) }}</a>
    @endforeach
    <span class="tabs-spacer"></span>
    <a href="{{ route('tickets.create', ['client' => $site->client_id]) }}" class="btn btn-small btn-dark"><x-icon name="plus" :size="15" /> {{ __('ui.new_ticket_short') }}</a>
</nav>

@if($tab === 'overview')
<div class="grid-main">
    <div>
        <section class="card">
            <div class="card-head"><h2><x-icon name="history" /> {{ __('site.log') }}</h2></div>
            <form method="post" action="{{ route('sites.logs.store', $site) }}" class="log-add">
                @csrf
                <select name="kind">@foreach(\App\Models\SiteLog::KINDS as $k)<option value="{{ $k }}">{{ __('site.log_'.$k) }}</option>@endforeach</select>
                <input type="date" name="logged_on" value="{{ today()->toDateString() }}" required>
                <input name="description" placeholder="{{ __('site.log_placeholder') }}" required>
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
            @forelse($logs as $l)
                <div class="log-row"><span class="log-kind kind-{{ $l->kind }}">{{ __('site.log_'.$l->kind) }}</span>
                    <span><small class="muted">{{ $l->logged_on->format('d.m.Y') }}@if($l->user) · {{ $l->user->name }}@endif @if($l->ticket) · <a href="{{ route('tickets.show', $l->ticket) }}">#{{ $l->ticket_id }}</a>@endif</small><br>{!! nl2br(e($l->description)) !!}</span></div>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </section>
        <section class="card">
            <h2><x-icon name="folder" /> {{ __('site.open_tickets') }}</h2>
            @forelse($openTickets as $t) @include('tickets._row', ['hideClient' => true]) @empty <p class="muted small">{{ __('ui.nothing_here') }}</p> @endforelse
        </section>
    </div>
    <aside>
        @if($site->care_hours)
            @php
    $pkg = (float) $site->care_hours * 60;
    $pct = min(100, (int) round($used / $pkg * 100));
@endphp
            <section class="card card-dark"><x-waves variant="card" />
                <h2>{{ __('site.package') }} — {{ $month->translatedFormat('F') }}</h2>
                <p class="dark-title">{{ \App\Models\TimeEntry::format($used) }} <small>/ {{ \App\Models\TimeEntry::format((int) $pkg) }} h</small></p>
                <div class="limit-bar dark"><span style="width: {{ $pct }}%"></span></div>
                <small>{{ __('site.package_hint') }}</small>
            </section>
        @endif
        <section class="card">
            <h2><x-icon name="calendar" /> {{ __('site.deadlines') }}</h2>
            @foreach($site->deadlines->whereNull('renewed_at') as $d)
                <div class="deadline">
                    <span @class(['date-tile', 'tile-red' => $d->daysLeft() <= 14])><strong>{{ $d->due_on->format('j') }}</strong><small>{{ $d->due_on->translatedFormat('M y') }}</small></span>
                    <span style="flex: 1"><strong>{{ __('site.kind_'.$d->kind) }}: {{ $d->name }}</strong><small class="muted">{{ trans_choice('site.days_left', $d->daysLeft(), ['n' => $d->daysLeft()]) }}@if($d->cost) · {{ number_format((float) $d->cost, 0, ',', ' ') }} zł @endif</small></span>
                    <form method="post" action="{{ route('deadlines.renew', $d) }}">@csrf<button class="btn btn-small" title="{{ __('site.renew_hint') }}">{{ __('site.renewed') }}</button></form>
                </div>
            @endforeach
            <form method="post" action="{{ route('sites.deadlines.store', $site) }}" class="deadline-add">
                @csrf
                <select name="kind">@foreach(\App\Models\SiteDeadline::KINDS as $k)<option value="{{ $k }}">{{ __('site.kind_'.$k) }}</option>@endforeach</select>
                <input name="name" placeholder="{{ __('site.deadline_name') }}" required>
                <input type="date" name="due_on" required>
                <input type="number" step="0.01" name="cost" placeholder="zł">
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
        </section>
        <section class="card">
            <h2><x-icon name="check-square" /> {{ __('site.reviews') }}</h2>
            <p class="small">{{ $site->review_recurrence ? __('site.rec_'.$site->review_recurrence) : __('site.no_reviews') }}@if($site->next_review_on) · {{ __('site.next_review') }}: <strong>{{ $site->next_review_on->format('d.m.Y') }}</strong>@endif</p>
            <form method="post" action="{{ route('sites.reviews.store', $site) }}">@csrf<button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /> {{ __('site.start_review') }}</button></form>
        </section>
        @if($site->notes)<section class="card"><h2>{{ __('site.notes') }}</h2><p class="small">{!! nl2br(e($site->notes)) !!}</p></section>@endif
    </aside>
</div>

@elseif($tab === 'reviews')
<section class="card">
    <div class="card-head"><h2>{{ __('site.reviews') }}</h2>
        <form method="post" action="{{ route('sites.reviews.store', $site) }}">@csrf<button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /> {{ __('site.start_review') }}</button></form></div>
    @forelse($reviews as $r)
        @php
    [$done, $all] = $r->progress();
@endphp
        <a href="{{ route('sites.reviews.show', $r) }}" class="site-row">
            <span @class(['date-tile', 'tile-green' => $r->completed_at])><strong>{{ $r->due_on->format('j') }}</strong><small>{{ $r->due_on->translatedFormat('M') }}</small></span>
            <span class="site-main"><strong>{{ __('site.review') }} {{ $r->due_on->translatedFormat('F Y') }}</strong><small class="muted">{{ $done }} / {{ $all }} {{ __('site.points') }}</small></span>
            <span class="pill {{ $r->completed_at ? 'pill-done' : 'pill-with_agency' }}">{{ $r->completed_at ? __('site.completed') : __('site.in_progress') }}</span>
        </a>
    @empty
        <p class="muted">{{ __('ui.nothing_here') }}</p>
    @endforelse
</section>

@elseif($tab === 'seo')
<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2><x-icon name="search" /> {{ __('site.phrases') }}</h2></div>
        <form method="post" action="{{ route('sites.positions.store', $site) }}">
            @csrf
            <table class="table phrase-table">
                <thead><tr><th>{{ __('site.phrase') }}</th><th></th><th>{{ __('site.position') }}</th><th>{{ __('site.new_measurement') }}</th></tr></thead>
                <tbody>
                @forelse($phrases as $p)
                    @php
    [$last, $change] = $p->latestWithChange();
@endphp
                    <tr>
                        <td><strong>{{ $p->phrase }}</strong><small class="muted d-block">P{{ $p->priority }} @if($p->target_url)· {{ $p->target_url }}@endif</small></td>
                        <td>@include('sites._spark', ['positions' => $p->positions])</td>
                        <td><strong>{{ $last?->position ?? '—' }}</strong>@if($change !== null)<span @class(['trend-pill sm', 'down' => $change < 0])>{{ $change > 0 ? '↑ +'.$change : ($change < 0 ? '↓ '.$change : '=') }}</span>@endif</td>
                        <td><input type="number" min="1" max="1000" name="pos[{{ $p->id }}]" class="num-input" placeholder="{{ $last?->position }}"></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('ui.nothing_here') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            @if($phrases->isNotEmpty())<div class="inline-form"><input type="date" name="measured_on" value="{{ today()->toDateString() }}" required><button class="btn btn-small btn-dark">{{ __('site.save_positions') }}</button></div>@endif
        </form>
        <form method="post" action="{{ route('sites.phrases.store', $site) }}" class="phrase-add">
            @csrf<input name="phrase" placeholder="{{ __('site.phrase') }}" required><input name="target_url" placeholder="{{ __('site.target_url') }}"><select name="priority"><option value="1">P1</option><option value="2" selected>P2</option><option value="3">P3</option></select>
            <button class="btn btn-small"><x-icon name="plus" :size="14" /></button>
        </form>
        <form method="post" action="{{ route('sites.positions.import', $site) }}" enctype="multipart/form-data" class="inline-form small">
            @csrf<span class="muted">{{ __('site.import_hint') }}</span><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-small">{{ __('site.import') }}</button>
        </form>
    </section>
    <div>
        <section class="card">
            <h2><x-icon name="file-text" /> {{ __('site.content_plan') }}</h2>
            @foreach($contents as $c)
                <a href="{{ route('tickets.show', $c->ticket) }}" class="side-file">
                    <span><strong>{{ $c->working_title }}</strong>@if($c->phrase)<small class="muted d-block">„{{ $c->phrase->phrase }}”</small>@endif</span>
                    <span class="pill pill-{{ $c->ticket->status->value }}">{{ $c->ticket->status->label() }}</span>
                    <small class="muted">{{ $c->due_on?->format('d.m') }}</small>
                </a>
            @endforeach
            <form method="post" action="{{ route('sites.seo-contents.store', $site) }}" class="phrase-add">
                @csrf<input name="working_title" placeholder="{{ __('site.working_title') }}" required>
                <select name="seo_phrase_id"><option value="">{{ __('site.phrase') }}…</option>@foreach($phrases as $p)<option value="{{ $p->id }}">{{ $p->phrase }}</option>@endforeach</select>
                <input type="date" name="due_on"><button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
        </section>
        <section class="card">
            <h2><x-icon name="code" /> {{ __('site.tech_tasks') }}</h2>
            @foreach($tasks as $t)
                <div class="todo-row">
                    <span class="prio-circle prio-{{ $t->priority === 1 ? 'urgent' : ($t->priority === 2 ? 'high' : 'normal') }}"></span>
                    <span class="todo-text">{{ $t->description }}</span>
                    <form method="post" action="{{ route('seo-tasks.update', $t) }}">@csrf @method('patch')
                        <select name="status" onchange="this.form.submit()">@foreach(['todo', 'doing', 'done'] as $st)<option value="{{ $st }}" @selected($t->status === $st)>{{ __('site.task_'.$st) }}</option>@endforeach</select></form>
                </div>
            @endforeach
            <form method="post" action="{{ route('sites.seo-tasks.store', $site) }}" class="phrase-add">
                @csrf<input name="description" placeholder="{{ __('site.task_placeholder') }}" required><select name="priority"><option value="1">P1</option><option value="2" selected>P2</option><option value="3">P3</option></select>
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
        </section>
        <section class="card">
            <h2><x-icon name="link" /> {{ __('site.backlinks') }}</h2>
            @foreach($backlinks as $b)
                <div @class(['side-file', 'inactive' => ! $b->is_active])><span><a href="{{ $b->url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($b->url, 45) }}</a><small class="muted d-block">{{ $b->placed_on?->format('d.m.Y') }} · {{ $b->cost ? number_format((float) $b->cost, 0, ',', ' ').' zł' : '—' }} · {{ __('site.checked') }} {{ $b->checked_on?->format('d.m') }}</small></span>
                    <form method="post" action="{{ route('backlinks.toggle', $b) }}">@csrf @method('patch')<button class="pill {{ $b->is_active ? 'pill-done' : 'pill-pink' }}">{{ $b->is_active ? __('site.works') : __('site.broken') }}</button></form></div>
            @endforeach
            <form method="post" action="{{ route('sites.backlinks.store', $site) }}" class="phrase-add">
                @csrf<input name="url" type="url" placeholder="https://" required><input type="date" name="placed_on"><input type="number" step="0.01" name="cost" placeholder="zł">
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
        </section>
    </div>
</div>

@else
<div class="page-head">
    <div class="week-nav">
        <a href="{{ route('sites.show', [$site, 'tab' => 'report', 'month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="16" /></a>
        <strong class="month-label">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        <a href="{{ route('sites.show', [$site, 'tab' => 'report', 'month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-right" :size="16" /></a>
    </div>
    <span class="muted small">{{ __('site.report_hint') }}</span>
</div>
@include('sites._report')
@endif
@endsection
