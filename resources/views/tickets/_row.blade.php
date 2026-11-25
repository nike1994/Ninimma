{{-- Wiersz zlecenia: ikona typu, tytuł, typ, status, data, "Otwórz". --}}
@php($me = auth()->user())
<div class="trow">
    <x-type-icon :type="$t->type" />
    <a href="{{ route('tickets.show', $t) }}" class="trow-main">
        <strong>@if($t->hasUnreadFor($me))<span class="dot"></span>@endif{{ $t->title }}</strong>
        <small class="muted">#{{ $t->id }}@if($me->isAdmin() && empty($hideClient)) · {{ $t->client->name }}@endif</small>
    </a>
    <span class="tag hide-sm">{{ $t->type->label() }}</span>
    <span class="trow-status">
        @if($t->isStalled())<span class="pill pill-pink">{{ __('ui.stalled') }}</span>@endif
        <span class="pill pill-{{ $t->status->value }}"><i></i>{{ $t->status->label() }}</span>
    </span>
    <span class="trow-date hide-sm"><x-icon name="calendar" :size="16" />{{ $t->last_activity_at?->translatedFormat('j M') }}</span>
    <a href="{{ route('tickets.show', $t) }}" class="btn btn-dark btn-small">{{ __('ui.open_btn') }} <x-icon name="chevron-right" :size="15" /></a>
</div>
