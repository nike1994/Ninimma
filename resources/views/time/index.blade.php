@extends('layouts.app')
@section('title', __('task.time_title'))
@section('content')
@php
    $fmt = fn ($m) => $m ? \App\Models\TimeEntry::format((int) $m) : '';
    $zl = fn ($v) => number_format((float) $v, 0, ',', ' ').' zł';
    $days = collect(range(0, 6))->map(fn ($i) => $monday->copy()->addDays($i));
@endphp
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="clock" :size="26" /></span>
        <div><h1>{{ __('task.time_title') }}</h1><p class="muted">{{ __('task.time_subtitle') }}</p></div></div>
    <div class="dash-head-right">
        <a href="{{ route('tasks.index') }}" class="btn"><x-icon name="check-square" :size="16" /> {{ __('task.title') }}</a>
        <a href="{{ route('time.export', ['month' => $month->format('Y-m')]) }}" class="btn"><x-icon name="download" :size="16" /> {{ __('task.export') }} · {{ $month->translatedFormat('F Y') }}</a>
    </div>
</div>

<nav class="mk-tabs time-tabs">
    @foreach(['week', 'team', 'clients'] as $tb)
        <a href="{{ route('time.index', array_filter(['tab' => $tb === 'week' ? null : $tb, 'week' => $tb !== 'clients' ? request('week') : null, 'month' => $tb === 'clients' ? request('month') : null])) }}" @class(['on' => $tab === $tb])>{{ __('task.tab_'.$tb) }}</a>
    @endforeach
</nav>

@if($tab !== 'clients')
    <div class="time-period">
        <a href="{{ route('time.index', array_filter(['tab' => $tab === 'week' ? null : $tab, 'user' => $tab === 'week' && $person->id !== $me->id ? $person->id : null, 'week' => $monday->copy()->subWeek()->toDateString()])) }}" class="btn btn-small">‹ {{ __('task.prev') }}</a>
        <strong>{{ __('task.week_of', ['from' => $monday->translatedFormat('j M'), 'to' => $monday->copy()->addDays(6)->translatedFormat('j M Y')]) }}</strong>
        <a href="{{ route('time.index', array_filter(['tab' => $tab === 'week' ? null : $tab, 'user' => $tab === 'week' && $person->id !== $me->id ? $person->id : null, 'week' => $monday->copy()->addWeek()->toDateString()])) }}" class="btn btn-small">{{ __('task.next') }} ›</a>
        @unless($monday->isSameDay(today()->startOfWeek()))<a href="{{ route('time.index', array_filter(['tab' => $tab === 'week' ? null : $tab])) }}" class="link-arrow small">{{ __('task.this_week') }}</a>@endunless
        @if($tab === 'week')
            <form class="time-person" action="{{ route('time.index') }}"><input type="hidden" name="week" value="{{ $monday->toDateString() }}">
                <select name="user" onchange="this.form.submit()" aria-label="{{ __('task.person') }}">@foreach($team as $u)<option value="{{ $u->id }}" @selected($u->id === $person->id)>{{ $u->name }}</option>@endforeach</select></form>
        @endif
    </div>
@else
    <div class="time-period">
        <a href="{{ route('time.index', ['tab' => 'clients', 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-small">‹ {{ __('task.prev') }}</a>
        <strong>{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        <a href="{{ route('time.index', ['tab' => 'clients', 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-small">{{ __('task.next') }} ›</a>
        @unless($month->isSameMonth(today()))<a href="{{ route('time.index', ['tab' => 'clients']) }}" class="link-arrow small">{{ __('task.this_month') }}</a>@endunless
    </div>
@endif

@if($tab === 'week')
    @php($rows = $week['rows'])
    <section class="card">
        <div class="table-scroll"><table class="table time-grid">
            <thead><tr><th>{{ __('task.ticket') }}</th>@foreach($days as $d)<th @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>{{ $d->translatedFormat('D') }}<small>{{ $d->format('d.m') }}</small></th>@endforeach<th>{{ __('task.total') }}</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr><td><a href="{{ route('tickets.show', $r['ticket']) }}" class="time-ticket"><strong>{{ $r['ticket']->title }}</strong><small class="muted">{{ $r['ticket']->client->name }}</small></a></td>
                    @foreach($days as $d)<td @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>{{ $fmt($r['days'][$d->toDateString()] ?? 0) }}</td>@endforeach
                    <td><strong>{{ $fmt($r['total']) }}</strong></td></tr>
            @empty
                <tr><td colspan="9" class="muted">{{ __('task.no_entries') }}</td></tr>
            @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot><tr><th>{{ __('task.total') }}</th>
                @foreach($days as $d)<th @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>{{ $fmt($rows->sum(fn ($r) => $r['days'][$d->toDateString()] ?? 0)) }}</th>@endforeach
                <th>{{ $fmt($rows->sum('total')) }}</th></tr></tfoot>
            @endif
        </table></div>
    </section>

    <div class="grid-2 time-cols">
        <section class="card">
            <h2><x-icon name="plus" /> {{ __('task.add_entry') }}</h2>
            <form method="post" action="{{ route('time.store') }}" class="stack">
                @csrf
                <label class="field"><span>{{ __('task.ticket') }}</span>
                    <select name="ticket_id" required>@foreach($tickets as $t)<option value="{{ $t->id }}" @selected(old('ticket_id') == $t->id)>{{ $t->client->name }} — {{ \Illuminate\Support\Str::limit($t->title, 60) }}</option>@endforeach</select></label>
                <div class="form-row">
                    <x-field name="hours" :label="__('task.hours')" placeholder="1:30" required />
                    <x-field name="spent_on" type="date" :label="__('ui.date')" :value="today()->toDateString()" :max="today()->toDateString()" required />
                    <div></div>
                </div>
                <x-field name="note" :label="__('task.note')" :placeholder="__('ui.what_done')" />
                <label class="check"><input type="checkbox" name="internal" value="1"> {{ __('task.internal') }}</label>
                <button class="btn btn-dark btn-small">{{ __('ui.add') }}</button>
            </form>
        </section>
        <section class="card">
            <h2><x-icon name="list" /> {{ __('task.entries') }}</h2>
            @forelse($week['entries'] as $e)
                <details class="time-entry">
                    <summary><span class="te-date">{{ $e->spent_on->translatedFormat('D j.m') }}</span>
                        <span class="te-main"><strong>{{ \Illuminate\Support\Str::limit($e->ticket->title, 48) }}</strong><small class="muted">{{ $e->ticket->client->name }}@if($e->note) · {{ $e->note }}@endif</small></span>
                        <span class="te-badges">@if($e->source === 'timer')<span class="pill">{{ __('task.source_timer') }}</span>@endif @unless($e->billable)<span class="pill pill-pink">{{ __('task.internal_short') }}</span>@endunless</span>
                        <strong class="te-time">{{ $fmt($e->minutes) }}</strong></summary>
                    <form method="post" action="{{ route('time.update', $e) }}" class="time-form">@csrf @method('patch')
                        <input name="hours" value="{{ \App\Models\TimeEntry::format($e->minutes) }}" size="5" required aria-label="{{ __('task.hours') }}">
                        <input name="note" value="{{ $e->note }}" placeholder="{{ __('task.note') }}">
                        <label class="check small"><input type="checkbox" name="internal" value="1" @checked(! $e->billable)> {{ __('task.internal_short') }}</label>
                        <button class="btn btn-small btn-dark">{{ __('ui.save') }}</button></form>
                    <form method="post" action="{{ route('time.destroy', $e) }}" onsubmit="return confirm('{{ __('task.delete_confirm') }}')">@csrf @method('delete')<button class="btn btn-small btn-link-danger">{{ __('task.delete') }}</button></form>
                </details>
            @empty<p class="muted small">{{ __('task.no_entries') }}</p>@endforelse
        </section>
    </div>

@elseif($tab === 'team')
    <section class="card">
        <div class="table-scroll"><table class="table time-grid">
            <thead><tr><th>{{ __('task.person') }}</th>@foreach($days as $d)<th @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>{{ $d->translatedFormat('D') }}<small>{{ $d->format('d.m') }}</small></th>@endforeach<th>{{ __('task.total') }}</th><th>{{ __('task.billable') }}</th><th>{{ __('task.running_now') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><a href="{{ route('time.index', ['user' => $r->user->id, 'week' => $monday->toDateString()]) }}" class="time-person-cell"><x-avatar :name="$r->user->name" :size="28" />{{ $r->user->name }}</a></td>
                    @foreach($days as $d)@php($m = $r->days[$d->toDateString()] ?? 0)<td @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>@if($m)<span class="time-cell" style="--h: {{ min(100, $m / 480 * 100) }}%">{{ $fmt($m) }}</span>@endif</td>@endforeach
                    <td><strong>{{ $fmt($r->total) ?: '0:00' }}</strong></td>
                    <td>@if($r->total){{ (int) round($r->billable / $r->total * 100) }}%@else — @endif</td>
                    <td class="small">@if($r->timer)<span class="rec"></span> {{ \Illuminate\Support\Str::limit($r->timer->title, 28) }}@endif</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

@else
    <section class="card">
        <p class="muted small">{{ __('task.rate_hint') }}</p>
        <div class="table-scroll"><table class="table time-clients">
            <thead><tr><th>{{ __('ui.client') }}</th><th>{{ __('task.total') }}</th><th>{{ __('task.billable') }}</th><th>{{ __('task.care_package') }}</th><th>{{ __('task.rate') }}</th><th>{{ __('task.value') }}</th></tr></thead>
            <tbody>
            @forelse($clients as $c)
                <tr class="time-client-row"><td><details><summary><strong>{{ $c->client->name }}</strong> <small class="muted">({{ $c->tickets->count() }})</small></summary>
                        <ul class="time-client-tickets">@foreach($c->tickets as $tk)<li><a href="{{ route('tickets.show', $tk->ticket) }}">{{ \Illuminate\Support\Str::limit($tk->ticket->title, 60) }}</a><span>{{ $fmt($tk->total) }}</span></li>@endforeach</ul></details></td>
                    <td>{{ $fmt($c->total) }}</td><td>{{ $fmt($c->billable) ?: '0:00' }}</td>
                    <td>@if($c->care_minutes)@php($pct = (int) round($c->care_used / $c->care_minutes * 100))<span @class(['overdue' => $pct > 100])>{{ __('task.care_of', ['used' => \App\Models\TimeEntry::hours($c->care_used), 'package' => \App\Models\TimeEntry::hours($c->care_minutes)]) }} ({{ $pct }}%)</span>@else<span class="muted">—</span>@endif</td>
                    <td class="nowrap">{{ $zl($c->rate) }}/h</td><td class="nowrap"><strong>{{ $zl($c->value) }}</strong></td></tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('task.no_entries') }}</td></tr>
            @endforelse
            </tbody>
            @if($clients->isNotEmpty())
            <tfoot><tr><th>{{ __('task.total') }}</th><th>{{ $fmt($clients->sum('total')) }}</th><th>{{ $fmt($clients->sum('billable')) }}</th><th></th><th></th><th class="nowrap">{{ $zl($clients->sum('value')) }}</th></tr></tfoot>
            @endif
        </table></div>
    </section>
@endif
@endsection
