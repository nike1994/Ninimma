{{-- Wiersz zlecenia: ikona typu, tytuł, typ, status, data, "Otwórz". --}}
@php($me = auth()->user())
<div class="trow">
    <x-type-icon :type="$t->type" />
    <a href="{{ route('tickets.show', $t) }}" class="trow-main">
        <strong>
            @if($t->hasUnreadFor($me))<span class="dot"></span>@endif
            @if($t->priority->isElevated())<x-icon name="flag" :size="15" class="flag-{{ $t->priority->value }}" />@endif
            {{ $t->title }}
        </strong>
        <small class="muted">#{{ $t->id }} · {{ $t->type->label() }}@if($me->isAdmin() && empty($hideClient)) · {{ $t->client->name }}@endif</small>
    </a>
    <span class="trow-status">
        @if($t->isStalled())<span class="pill pill-pink">{{ __('ui.stalled') }}</span>@endif
        <span class="pill pill-{{ $t->status->value }}"><i></i>{{ $t->status->label() }}</span>
    </span>
    @if($t->due_on)
        <span @class(['trow-date hide-sm', 'overdue' => $t->isOverdue()]) title="{{ __('ui.due_on') }}"><x-icon name="calendar" :size="16" />{{ $t->due_on->translatedFormat('j M') }}</span>
    @else
        <span class="trow-date hide-sm muted" title="{{ __('ui.last_activity') }}"><x-icon name="clock" :size="16" />{{ $t->last_activity_at?->translatedFormat('j M') }}</span>
    @endif
    <a href="{{ route('tickets.show', $t) }}" class="btn btn-dark btn-small">{{ __('ui.open_btn') }} <x-icon name="chevron-right" :size="15" /></a>
</div>
