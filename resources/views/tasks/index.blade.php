@extends('layouts.app')
@section('title', __('task.title'))
@section('content')
@php($q = fn (array $over = []) => array_filter(array_merge(['who' => $who !== 'me' ? $who : null, 'client' => $clientId, 'view' => $view !== 'list' ? $view : null, 'type' => request('type'), 'q' => request('q')], $over), fn ($v) => $v !== null && $v !== ''))
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="check-square" :size="26" /></span>
        <div><h1>{{ __('task.title') }}</h1><p class="muted">{{ __('task.subtitle') }}</p></div></div>
    <div class="dash-head-right"><a href="{{ route('time.index') }}" class="btn"><x-icon name="clock" :size="16" /> {{ __('task.time_title') }}</a></div>
</div>

<div class="kpis task-kpis">
    <a href="{{ route('tasks.index') }}" class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="user-plus" :size="24" /></span><div><span class="kpi-label">{{ __('task.kpi_mine') }}</span><div class="kpi-value"><strong>{{ $kpi['mine'] }}</strong></div></div></div><x-waves variant="card" /></a>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="alert" :size="24" /></span><div><span class="kpi-label">{{ __('task.kpi_overdue') }}</span><div class="kpi-value"><strong @class(['overdue' => $kpi['overdue'] > 0])>{{ $kpi['overdue'] }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="calendar" :size="24" /></span><div><span class="kpi-label">{{ __('task.kpi_today') }}</span><div class="kpi-value"><strong>{{ $kpi['today'] }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="message" :size="24" /></span><div><span class="kpi-label">{{ __('task.kpi_waiting') }}</span><div class="kpi-value"><strong>{{ $kpi['waiting'] }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <a href="{{ route('time.index') }}" class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="clock" :size="24" /></span><div><span class="kpi-label">{{ __('task.kpi_week') }}</span><div class="kpi-value"><strong>{{ \App\Models\TimeEntry::hours($kpi['week_minutes']) }}</strong></div></div></div><x-waves variant="kpi" /></a>
</div>

<form method="post" action="{{ route('tasks.store') }}" class="card task-add">
    @csrf
    <x-icon name="plus" :size="18" />
    <input name="title" placeholder="{{ __('task.title_ph') }}" required maxlength="200" aria-label="{{ __('task.quick_add') }}">
    <select name="client_id" required aria-label="{{ __('ui.client') }}">@foreach($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select>
    <input type="date" name="due_on" aria-label="{{ __('ui.due_on') }}" title="{{ __('ui.due_on') }}">
    <select name="assigned_to" aria-label="{{ __('ui.assignee') }}">@foreach($team as $u)<option value="{{ $u->id }}" @selected($u->id === $me->id)>{{ $u->name }}</option>@endforeach</select>
    <select name="priority" aria-label="{{ __('ui.priority') }}">@foreach(\App\Enums\Priority::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
    <input name="estimate" placeholder="{{ __('task.estimate_ph') }}" size="6" pattern="\d{1,3}([:.,]\d{1,2})?" title="{{ __('task.estimate') }}" aria-label="{{ __('task.estimate') }}">
    <button class="btn btn-send btn-small">{{ __('ui.add') }}</button>
</form>

<div class="social-filters task-filters">
    @foreach(['me' => __('task.who_me'), 'all' => __('task.who_all'), 'none' => __('task.who_none')] as $k => $label)
        <a href="{{ route('tasks.index', $q(['who' => $k === 'me' ? null : $k])) }}" @class(['chip', 'chip-dark' => $who === $k])>{{ $label }}</a>
    @endforeach
    @foreach($team->where('id', '!=', $me->id) as $u)
        <a href="{{ route('tasks.index', $q(['who' => $u->id])) }}" @class(['chip', 'chip-dark' => (string) $who === (string) $u->id])><x-avatar :name="$u->name" :size="22" />{{ \Illuminate\Support\Str::before($u->name, ' ') }}</a>
    @endforeach
    <form class="task-filter-form" action="{{ route('tasks.index') }}">
        @foreach($q(['client' => null, 'q' => null, 'type' => null]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <select name="client" onchange="this.form.submit()" aria-label="{{ __('ui.client') }}"><option value="">{{ __('task.all_clients') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="type" onchange="this.form.submit()" aria-label="{{ __('task.type') }}"><option value="">{{ __('task.all_types') }}</option>@foreach(\App\Enums\TicketType::cases() as $tp)<option value="{{ $tp->value }}" @selected(request('type') === $tp->value)>{{ $tp->label() }}</option>@endforeach</select>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('task.search') }}">
    </form>
    <span class="mk-view">
        <a href="{{ route('tasks.index', $q(['view' => null])) }}" @class(['on' => $view === 'list']) title="{{ __('task.view_list') }}"><x-icon name="list" :size="18" /></a>
        <a href="{{ route('tasks.index', $q(['view' => 'board'])) }}" @class(['on' => $view === 'board']) title="{{ __('task.view_board') }}"><x-icon name="grid" :size="18" /></a>
    </span>
</div>

@if($view === 'list')
    @php($any = collect($groups)->sum(fn ($g) => $g->count()))
    @if(! $any)<section class="card"><p class="muted">{{ __('task.empty_all') }}</p></section>@endif
    @foreach($groups as $key => $items)
        @continue($items->isEmpty())
        <section class="card task-group task-group-{{ $key }}">
            <h2>{{ __('task.g_'.$key) }} <span class="count-badge">{{ $items->count() }}</span>
                @php($est = $items->sum('estimate_minutes'))@if($est)<small class="muted">· {{ __('task.estimate') }} {{ \App\Models\TimeEntry::hours($est) }}</small>@endif</h2>
            @foreach($items as $t)@include('tasks._task')@endforeach
        </section>
    @endforeach
@else
    <div class="task-board">
        @foreach($columns as $key => $items)
            <section class="task-col task-col-{{ $key }}">
                <h2>{{ __('task.c_'.$key) }} <span class="count-badge">{{ $items->count() }}</span></h2>
                @forelse($items as $t)@include('tasks._task', ['compact' => true])
                @empty<p class="muted small">{{ __('task.empty') }}</p>@endforelse
            </section>
        @endforeach
    </div>
@endif
@endsection
