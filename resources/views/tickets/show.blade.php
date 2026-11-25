@extends('layouts.app')
@section('title', '#'.$ticket->id.' '.$ticket->title)
@section('content')
@php($me = auth()->user())
<div class="ticket-head">
    <div>
        <p class="muted small">
            #{{ $ticket->id }} · {{ $ticket->type->label() }}
            @if($me->isAdmin()) · <a href="{{ route('admin.clients.show', $ticket->client) }}">{{ $ticket->client->name }}</a>@endif
            @if($ticket->parent) · {{ __('ui.follows_from') }} <a href="{{ route('tickets.show', $ticket->parent) }}">#{{ $ticket->parent->id }}</a>@endif
        </p>
        <h1>{{ $ticket->title }}</h1>
        <p><x-status :ticket="$ticket" />
            @if($ticket->blocked_reason)<span class="muted">— {{ $ticket->blocked_reason }}</span>@endif
            @if($ticket->status->ball())<span class="muted small">· {{ __('ui.ball_on_'.$ticket->status->ball()) }}</span>@endif
            @if($ticket->archive_state !== 'active')<span class="badge">{{ __('ui.archive_'.$ticket->archive_state) }}</span>@endif
        </p>
    </div>
    <a class="btn btn-small" href="{{ route('tickets.create', ['parent' => $ticket->id]) }}">{{ __('ui.new_follow_up') }}</a>
</div>

@if($me->isAdmin())
<form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="card inline-form" data-status-form>
    @csrf @method('patch')
    <select name="status">
        @foreach(\App\Enums\TicketStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>
        @endforeach
    </select>
    <input name="blocked_reason" value="{{ $ticket->blocked_reason }}" placeholder="{{ __('ui.blocked_reason') }}" data-reason>
    <button class="btn btn-small">{{ __('ui.change_status') }}</button>
    @if($ticket->status->waitsForClient())
        <button class="btn btn-small" form="remind-form">{{ __('ui.remind') }}</button>
        <span class="muted small">{{ trans_choice('ui.reminders_sent', $ticket->reminders_sent, ['n' => $ticket->reminders_sent]) }}</span>
    @endif
</form>
<form method="post" action="{{ route('admin.tickets.remind', $ticket) }}" id="remind-form" hidden>@csrf</form>
@endif

<div class="ticket-layout">
    <section class="thread-wrap">
        <div class="thread" data-thread
             data-poll="{{ route('tickets.poll', $ticket) }}"
             data-last-id="{{ $lastId }}"
             data-active-ms="{{ config('agency.poll_active_ms') }}"
             data-idle-ms="{{ config('agency.poll_idle_ms') }}"
             data-seen-label="{{ __('ui.seen_by') }}">
            @forelse($messages as $message)
                @include('tickets._message')
            @empty
                <p class="muted empty">{{ __('ui.no_messages') }}</p>
            @endforelse
        </div>

        @can('reply', $ticket)
        <form method="post" action="{{ route('tickets.messages.store', $ticket) }}" class="composer" data-message-form data-ajax>
            @csrf
            <textarea name="body" rows="3" placeholder="{{ __('ui.write_message') }}" data-paste-target></textarea>
            @include('tickets._uploader')
            <div class="composer-actions">
                @if($me->isAdmin())
                    <label class="check"><input type="checkbox" name="internal" value="1" data-internal> {{ __('ui.internal_note') }}</label>
                @endif
                <button class="btn btn-primary">{{ __('ui.send') }}</button>
            </div>
        </form>
        @endcan
    </section>

    <aside class="card timeline">
        <h2>{{ __('ui.timeline') }}</h2>
        <ol>
            @foreach($events as $e)
                <li @class(['internal' => $e->is_internal])>
                    <time class="small muted">{{ $e->created_at->format('Y-m-d H:i') }}</time>
                    <span>{{ $e->description() }}</span>
                    @if($e->user)<span class="small muted">— {{ $e->user->name }}</span>@endif
                </li>
            @endforeach
        </ol>
        @if($ticket->children->isNotEmpty())
            <h3>{{ __('ui.follow_ups') }}</h3>
            <ul>@foreach($ticket->children as $c)<li><a href="{{ route('tickets.show', $c) }}">#{{ $c->id }} {{ $c->title }}</a></li>@endforeach</ul>
        @endif
        @if($me->isAdmin() && $clientReads->isNotEmpty())
            <h3>{{ __('ui.client_reads') }}</h3>
            <ul class="small">@foreach($clientReads as $r)<li>{{ $r->user->name }} — {{ $r->seen_at->format('Y-m-d H:i') }}</li>@endforeach</ul>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/live.js') }}?v=1"></script>
<script src="{{ asset('js/uploader.js') }}?v=1"></script>
<script src="{{ asset('js/ticket.js') }}?v=1"></script>
@endpush
