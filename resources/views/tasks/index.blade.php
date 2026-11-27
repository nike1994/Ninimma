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

<section class="card task-add" data-task-add data-parse-url="{{ route('tasks.parse') }}">
    <form method="post" action="{{ route('tasks.store') }}" class="task-add-form">
        @csrf
        <x-icon name="plus" :size="18" />
        <textarea name="lines" rows="1" required maxlength="20000" placeholder="{{ __('task.line_ph') }}" aria-label="{{ __('task.quick_add') }}" data-lines>{{ old('lines') }}</textarea>
        <select name="client_id" aria-label="{{ __('task.default_client') }}" title="{{ __('task.default_client') }}" data-default-client>
            <option value="">{{ __('task.client_from_line') }}</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected((int) old('client_id', $defaultClient) === $c->id)>{{ $c->name }}</option>@endforeach</select>
        <button class="btn btn-send btn-small" data-add-btn>{{ __('ui.add') }}</button>
    </form>
    <ul class="task-preview" data-preview hidden></ul>
    <p class="task-legend muted small">{!! __('task.legend') !!}</p>
    <details class="task-sets">
        <summary><x-icon name="layers" :size="15" /> {{ __('task.sets') }} <span class="muted">({{ $sets->count() }})</span></summary>
        <p class="muted small">{{ __('task.sets_hint') }}</p>
        <div class="task-sets-list">
            @foreach($sets as $set)
                <div class="task-set">
                    <div class="task-set-head"><strong>{{ $set->name }}</strong><small class="muted">{{ trans_choice('task.n_tasks', count($set->lines()), ['n' => count($set->lines())]) }}</small></div>
                    <form method="post" action="{{ route('tasks.sets.apply', $set) }}" class="task-set-apply">
                        @csrf
                        <select name="client_id" required aria-label="{{ __('ui.client') }}" data-set-client>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($defaultClient == $c->id)>{{ $c->name }}</option>@endforeach</select>
                        <input type="date" name="start_on" value="{{ today()->toDateString() }}" required aria-label="{{ __('task.start_on') }}" title="{{ __('task.start_on') }}">
                        <select name="campaign_id" aria-label="{{ __('task.campaign_opt') }}" data-set-campaign><option value="">{{ __('task.campaign_opt') }}</option>@foreach($campaigns as $cp)<option value="{{ $cp->id }}" data-client="{{ $cp->client_id }}">{{ $cp->name }}</option>@endforeach</select>
                        <button class="btn btn-dark btn-small">{{ __('task.use_set') }}</button>
                    </form>
                    <details class="task-set-edit"><summary class="small">{{ __('task.edit_set') }}</summary>
                        <form method="post" action="{{ route('tasks.sets.update', $set) }}" class="stack">@csrf @method('put')
                            <input name="name" value="{{ $set->name }}" required maxlength="120">
                            <textarea name="body" rows="8" required>{{ $set->body }}</textarea>
                            <div class="head-actions"><button class="btn btn-small btn-dark">{{ __('ui.save') }}</button></div>
                        </form>
                        <form method="post" action="{{ route('tasks.sets.destroy', $set) }}" onsubmit="return confirm('{{ __('task.delete_set_confirm') }}')">@csrf @method('delete')<button class="btn btn-small btn-link-danger">{{ __('task.delete') }}</button></form>
                    </details>
                </div>
            @endforeach
            <details class="task-set task-set-new"><summary><x-icon name="plus" :size="14" /> {{ __('task.new_set') }}</summary>
                <form method="post" action="{{ route('tasks.sets.store') }}" class="stack">@csrf
                    <input name="name" required maxlength="120" placeholder="{{ __('task.set_name_ph') }}">
                    <textarea name="body" rows="7" required placeholder="{{ __('task.set_body_ph') }}"></textarea>
                    <div class="head-actions"><button class="btn btn-small btn-dark">{{ __('ui.save') }}</button></div>
                </form></details>
        </div>
    </details>
</section>

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
@push('scripts')
<script src="{{ asset('js/task-add.js') }}?v=1" defer></script>
@endpush
