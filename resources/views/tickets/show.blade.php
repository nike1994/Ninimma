@extends('layouts.app')
@section('title', '#'.$ticket->id.' '.$ticket->title)
@section('content')
@php
    $me = auth()->user();
    $client = $ticket->client;
    $attachments = $messages->flatMap->attachments;
    $keyEvents = $events->where('type', 'status_changed')->take(-2);
@endphp

@if($me->isAdmin())
<header class="client-head">
    <x-avatar :name="$client->name" :size="88" class="avatar-xl" />
    <div class="client-id">
        <h1>{{ $client->name }}</h1>
        <p class="muted">{{ $client->company_name ?: '—' }}<br>{{ __('ui.client_since', ['date' => $client->created_at->translatedFormat('F Y')]) }}</p>
    </div>
    <div class="client-contact">
        @if($client->contact_phone)<a href="tel:{{ $client->contact_phone }}"><x-icon name="phone" :size="18" />{{ $client->contact_phone }}</a>@endif
        @if($client->contact_email)<a href="mailto:{{ $client->contact_email }}"><x-icon name="mail" :size="18" />{{ $client->contact_email }}</a>@endif
    </div>
    <div class="client-badges">
        @if($ticket->isStalled())<span class="pill pill-pink pill-lg">{{ __('ui.stalled') }}</span>@endif
        @if($ticket->status === \App\Enums\TicketStatus::Blocked)<span class="pill pill-blocked pill-lg">{{ __('status.blocked') }}</span>@endif
        <span class="pill pill-lg pill-type">{{ $ticket->type->label() }}</span>
    </div>
</header>
@endif

<nav class="tabs" data-tabs>
    @if($me->isAdmin() && $client->contact_phone)<a class="round-btn" href="tel:{{ $client->contact_phone }}" title="{{ __('ui.contact_phone') }}"><x-icon name="phone" :size="18" /></a>@endif
    @if($me->isAdmin() && $client->contact_email)<a class="round-btn" href="mailto:{{ $client->contact_email }}" title="E-mail"><x-icon name="mail" :size="18" /></a>@endif
    @if($me->isAdmin())<a class="round-btn" href="{{ route('tickets.create', ['client' => $client->id]) }}" title="{{ __('ui.new_ticket') }}"><x-icon name="plus" :size="18" /></a>@endif
    <span class="tabs-spacer"></span>
    <button type="button" class="tab active" data-tab="ticket">{{ __('ui.ticket_no', ['n' => $ticket->id]) }}</button>
    <button type="button" class="tab" data-tab="history">{{ __('ui.history') }}</button>
    <button type="button" class="tab" data-tab="files">{{ __('ui.files') }} @if($attachments->count())<small>{{ $attachments->count() }}</small>@endif</button>
    <a class="tab" href="{{ $me->isAdmin() ? route('admin.clients.show', $client) : route('tickets.index') }}">{{ __('ui.other_tickets') }}</a>
</nav>

<div class="ticket-layout">
    <div class="ticket-main">
        {{-- Zakładka: zlecenie --}}
        <section class="panel" data-panel="ticket">
            <div class="card">
                <p class="muted small">#{{ $ticket->id }} · {{ $ticket->type->label() }}
                    @if($ticket->parent) · {{ __('ui.follows_from') }} <a href="{{ route('tickets.show', $ticket->parent) }}">#{{ $ticket->parent->id }} {{ $ticket->parent->title }}</a>@endif</p>
                <h2 class="ticket-title">{{ $ticket->title }}</h2>
                @foreach($keyEvents as $e)
                    <div class="event-card">
                        <span class="date-pill">{{ $e->created_at->translatedFormat('j M') }}</span>
                        <span class="event-dot"></span>
                        <span class="event-text"><strong>{{ $e->description() }}</strong>
                            <small class="muted">{{ $e->user?->name ?? __('ui.system') }} · {{ $e->created_at->format('H:i') }}@if(!empty($e->data['reason'])) · {{ $e->data['reason'] }}@endif</small></span>
                        @if(!empty($e->data['to']))<span class="pill pill-{{ $e->data['to'] }}">{{ __('status.'.$e->data['to']) }}</span>@endif
                    </div>
                @endforeach
            </div>

            <div class="card chat">
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
                    @if($me->isAdmin())
                        <input type="hidden" name="internal" value="0" data-internal>
                        <div class="segmented" data-segmented>
                            <button type="button" class="active" data-value="0">{{ __('ui.message_to_client') }}</button>
                            <button type="button" data-value="1"><x-icon name="lock" :size="14" /> {{ __('ui.internal_note') }}</button>
                        </div>
                    @endif
                    <textarea name="body" rows="3" placeholder="{{ __('ui.write_message') }}" data-paste-target></textarea>
                    @include('tickets._uploader')
                </form>
                @endcan
            </div>
        </section>

        {{-- Zakładka: historia (oś czasu) --}}
        <section class="panel card" data-panel="history" hidden>
            <h2>{{ __('ui.timeline') }}</h2>
            <ol class="timeline">
                @foreach($events->reverse() as $e)
                    <li @class(['internal' => $e->is_internal])>
                        <span class="date-pill">{{ $e->created_at->format('d.m H:i') }}</span>
                        <span>{{ $e->description() }} @if($e->user)<span class="muted">— {{ $e->user->name }}</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Zakładka: pliki --}}
        <section class="panel card" data-panel="files" hidden>
            <h2>{{ __('ui.files') }}</h2>
            @forelse($attachments as $usage) @include('files._item') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
        </section>
    </div>

    <aside class="ticket-side">
        <section class="card card-dark">
            <x-waves variant="card" />
            <small class="overline">{{ __('ui.ticket_no', ['n' => $ticket->id]) }} · {{ $ticket->type->label() }}</small>
            <h2 class="dark-title" data-status-label>{{ $ticket->status->label() }}</h2>
            <div class="steps">
                @php($pos = ['with_agency' => 1, 'blocked' => 1, 'waiting_client' => 2, 'needs_decision' => 2, 'done' => 3][$ticket->status->value])
                @for($i = 1; $i <= 3; $i++)<span @class(['done' => $i < $pos, 'now' => $i === $pos])></span>@endfor
            </div>
            <dl class="dark-dl">
                <dt>{{ __('ui.created') }}</dt><dd>{{ $ticket->created_at->format('d.m.Y') }}</dd>
                <dt>{{ __('ui.last_activity') }}</dt><dd>{{ $ticket->last_activity_at?->diffForHumans() }}</dd>
                <dt>{{ __('ui.messages') }}</dt><dd>{{ $messages->where('is_internal', false)->count() }}</dd>
                @if($ticket->blocked_reason)<dt>{{ __('ui.blocked_reason') }}</dt><dd>{{ $ticket->blocked_reason }}</dd>@endif
            </dl>
        </section>

        @if($ticket->status->ball())
        <section class="card ball-card">
            <h3><x-icon name="clock" /> {{ __('ui.ball_on_'.$ticket->status->ball().'_title') }}</h3>
            @if($ticket->status->waitsForClient())
                <p class="muted small">{{ __('ui.waiting_since', ['time' => $ticket->waiting_since?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}.
                    {{ trans_choice('ui.reminders_sent', $ticket->reminders_sent, ['n' => $ticket->reminders_sent]) }}.
                    @if($me->isAdmin() && ! $ticket->stalled_at && $ticket->waiting_since)
                        @php($next = $ticket->reminders_sent === 0 ? $ticket->waiting_since->copy()->addHours(config('agency.stall_first_reminder_hours')) : $ticket->waiting_since->copy()->addDays(config('agency.stall_second_reminder_days')))
                        {{ __('ui.next_auto_reminder', ['time' => $next->format('d.m H:i')]) }}
                    @endif
                </p>
            @else
                <p class="muted small">{{ __('ui.ball_agency_hint') }}</p>
            @endif
            @if($me->isAdmin())
                <div class="btn-row">
                    @if($ticket->status->waitsForClient())
                        <form method="post" action="{{ route('admin.tickets.remind', $ticket) }}">@csrf<button class="btn btn-dark btn-small"><x-icon name="bell" :size="15" /> {{ __('ui.remind_now') }}</button></form>
                    @endif
                    <a href="{{ route('tickets.create', ['parent' => $ticket->id]) }}" class="btn btn-small"><x-icon name="plus" :size="15" /> {{ __('ui.new_follow_up') }}</a>
                </div>
            @endif
        </section>
        @endif

        @if($me->isAdmin())
        <form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="card stack" data-status-form>
            @csrf @method('patch')
            <h3><x-icon name="sliders" /> {{ __('ui.change_status') }}</h3>
            <select name="status">
                @foreach(\App\Enums\TicketStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <input name="blocked_reason" value="{{ $ticket->blocked_reason }}" placeholder="{{ __('ui.blocked_reason') }}" data-reason>
            <button class="btn btn-small">{{ __('ui.save') }}</button>
        </form>
        @else
            <a href="{{ route('tickets.create', ['parent' => $ticket->id]) }}" class="btn btn-block"><x-icon name="plus" :size="16" /> {{ __('ui.new_follow_up') }}</a>
        @endif

        <section class="card">
            <h3><x-icon name="paperclip" /> {{ __('ui.files') }}</h3>
            @forelse($attachments->take(5) as $a)
                <a href="{{ route('files.show', [$a, $a->name]) }}" target="_blank" class="side-file">
                    <x-icon :name="$a->file->isImage() ? 'image' : ($a->file->isVideo() ? 'video' : 'file')" :size="17" />
                    <span>{{ $a->name }}</span>
                    <small class="muted">{{ $a->uploader?->isAdmin() ? __('ui.from_us') : __('ui.from_client') }}</small>
                </a>
            @empty
                <p class="muted small">{{ __('ui.no_files') }}</p>
            @endforelse
        </section>

        @if($ticket->children->isNotEmpty())
        <section class="card">
            <h3><x-icon name="link" /> {{ __('ui.follow_ups') }}</h3>
            @foreach($ticket->children as $c)<a href="{{ route('tickets.show', $c) }}" class="side-file"><span>#{{ $c->id }} {{ $c->title }}</span></a>@endforeach
        </section>
        @endif

        @if($me->isAdmin() && $clientReads->isNotEmpty())
        <section class="card">
            <h3><x-icon name="eye" /> {{ __('ui.client_reads') }}</h3>
            @foreach($clientReads as $r)
                <div class="side-file"><x-avatar :name="$r->user->name" :size="26" /><span>{{ $r->user->name }}</span><small class="muted">{{ $r->seen_at->format('d.m H:i') }}</small></div>
            @endforeach
        </section>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/live.js') }}?v=2"></script>
<script src="{{ asset('js/uploader.js') }}?v=2"></script>
<script src="{{ asset('js/ticket.js') }}?v=2"></script>
@endpush
