{{-- Zadanie na liście / tablicy: zrobione, tytuł, klient, priorytet, termin, osoba, czas / szacunek, stoper. --}}
@php($running = $me->timer_ticket_id === $t->id)
@php($logged = (int) ($t->minutes ?? 0))
@php($done = $t->status === \App\Enums\TicketStatus::Done)
<div @class(['tk', 'tk-done' => $done, 'tk-running' => $running, 'tk-compact' => $compact ?? false])>
    <form method="post" action="{{ route('tasks.update', $t) }}" class="tk-check">@csrf @method('patch')
        <input type="hidden" name="done" value="{{ $done ? 0 : 1 }}">
        <button type="submit" title="{{ $done ? __('task.reopen') : __('task.mark_done') }}" aria-label="{{ $done ? __('task.reopen') : __('task.mark_done') }}">@if($done)<x-icon name="check" :size="14" />@endif</button></form>
    <div class="tk-main">
        <a href="{{ route('tickets.show', $t) }}" class="tk-title">@if($t->priority->isElevated())<x-icon name="flag" :size="14" class="flag-{{ $t->priority->value }}" />@endif{{ $t->title }}</a>
        <small class="tk-meta"><span class="tk-client">{{ $t->client->name }}</span> · {{ $t->type->label() }}
            @if($t->status->waitsForClient() || $t->status === \App\Enums\TicketStatus::Blocked)<span class="pill pill-{{ $t->status->value }}"><i></i>{{ $t->status->label() }}</span>@endif</small>
    </div>
    <span @class(['tk-due', 'overdue' => ! $done && $t->due_on?->lt(today()), 'today' => $t->due_on?->isToday()])>@if($t->due_on)<x-icon name="calendar" :size="14" />{{ $t->due_on->isToday() ? __('task.g_today') : $t->due_on->translatedFormat('j M') }}@endif</span>
    <span @class(['tk-time', 'over' => $t->estimate_minutes && $logged > $t->estimate_minutes]) title="{{ $t->estimate_minutes && $logged > $t->estimate_minutes ? __('task.over_estimate') : '' }}">
        @if($logged || $t->estimate_minutes)<x-icon name="clock" :size="14" />{{ \App\Models\TimeEntry::format($logged) }}@if($t->estimate_minutes)<small>/{{ \App\Models\TimeEntry::format($t->estimate_minutes) }}</small>@endif @endif</span>
    <form method="post" action="{{ route('tasks.update', $t) }}" class="tk-who">@csrf @method('patch')
        <select name="assigned_to" onchange="this.form.submit()" aria-label="{{ __('ui.assignee') }}" title="{{ $t->assignee?->name ?? __('task.who_none') }}">
            <option value="">—</option>@foreach($team as $u)<option value="{{ $u->id }}" @selected($t->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach</select>
        <x-avatar :name="$t->assignee?->name ?? '?'" :size="28" /></form>
    @unless($done)
        @if($running)
            <form method="post" action="{{ route('timer.stop') }}">@csrf<button class="btn btn-small tk-timer on" title="{{ __('task.stop') }}"><span class="rec"></span><span data-timer-since="{{ $me->timer_started_at->timestamp }}">0:00</span></button></form>
        @else
            <form method="post" action="{{ route('timer.start', $t) }}">@csrf<button class="btn btn-small tk-timer" title="{{ __('task.start') }}"><x-icon name="play" :size="13" /></button></form>
        @endif
    @endunless
</div>
