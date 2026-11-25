@extends('layouts.app')
@section('content')
<div class="greeting">
    <h1>{{ __('ui.good_day') }} 👋</h1>
    <p class="muted">{{ __('ui.summary_intro', ['app' => config('app.name')]) }}</p>
</div>

<div class="stats">
    <x-stat icon="folder" :value="$tickets->count()" :label="__('ui.active_tickets')" tone="green" :href="route('tickets.index', ['status' => 'open'])" />
    <x-stat icon="clock" :value="$waitingForMe->count()" :label="__('ui.waiting_for_you')" tone="amber" />
    <x-stat icon="upload" :value="$newFilesCount" :label="__('ui.new_files')" tone="blue" :href="route('files.index')" />
    <x-stat icon="message" :value="$unread->count()" :label="__('ui.new_messages')" tone="teal" />
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>{{ __('ui.active_tickets') }}</h2><a href="{{ route('tickets.index') }}" class="link-arrow">{{ __('ui.all_tickets') }} <x-icon name="arrow-right" :size="16" /></a></div>
        @forelse($tickets->take(5) as $t)
            <a href="{{ route('tickets.show', $t) }}" class="list-item">
                <x-type-icon :type="$t->type" />
                <span class="list-main"><strong>@if($t->hasUnreadFor(auth()->user()))<span class="dot"></span>@endif{{ $t->title }}</strong>
                    <small class="muted">{{ $t->type->label() }} · {{ $t->last_activity_at?->diffForHumans() }}</small></span>
                <span class="pill pill-{{ $t->status->value }}"><i></i>{{ $t->status->label() }}</span>
            </a>
        @empty
            <p class="muted">{{ __('ui.nothing_here') }}</p>
        @endforelse
    </section>

    <section class="card card-dark">
        <x-waves variant="card" />
        <h2>{{ __('ui.waiting_for_you') }}</h2>
        @forelse($waitingForMe as $t)
            <a href="{{ route('tickets.show', $t) }}" class="dark-item">
                <span><strong>{{ $t->title }}</strong><small>{{ $t->status->label() }} · {{ $t->waiting_since?->diffForHumans() }}</small></span>
                <x-icon name="chevron-right" />
            </a>
        @empty
            <div class="dark-empty"><x-icon name="check-circle" :size="28" /><span>{{ __('ui.nothing_waiting') }}</span></div>
        @endforelse
        <a href="{{ route('tickets.create') }}" class="btn btn-white">{{ __('ui.new_ticket') }} <x-icon name="plus" :size="16" /></a>
    </section>
</div>

<div class="grid-3">
    <section class="card">
        <div class="card-head"><h2>{{ __('ui.latest_files') }}</h2><a href="{{ route('files.index') }}" class="link-arrow">{{ __('ui.all_files') }} <x-icon name="arrow-right" :size="16" /></a></div>
        @forelse($files as $usage) @include('files._item') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
    </section>
    <section class="card">
        <div class="card-head"><h2>{{ __('ui.messages') }}</h2></div>
        @forelse($messages as $m)
            <a href="{{ route('tickets.show', $m->ticket).'#m'.$m->id }}" class="msg-preview">
                <x-avatar :name="$m->user?->name ?? '?'" :size="38" />
                <span><strong>{{ $m->user?->name }}</strong><small class="muted">{{ \Illuminate\Support\Str::limit($m->body, 80) }}</small></span>
                <small class="muted">{{ $m->created_at->diffForHumans(short: true) }}</small>
            </a>
        @empty
            <p class="muted">{{ __('ui.no_messages') }}</p>
        @endforelse
        <a href="{{ route('tickets.create') }}" class="btn btn-dark btn-block"><x-icon name="send" :size="17" /> {{ __('ui.write_message_btn') }}</a>
    </section>
    <section class="card">
        <div class="card-head"><h2>{{ __('ui.recently_done') }}</h2></div>
        <ol class="mini-timeline">
            @forelse($recentlyDone as $t)
                <li><span class="date-tile"><strong>{{ $t->closed_at?->format('d') }}</strong><small>{{ $t->closed_at?->translatedFormat('M') }}</small></span>
                    <a href="{{ route('tickets.show', $t) }}"><strong>{{ $t->title }}</strong><small class="muted">{{ $t->type->label() }}</small></a></li>
            @empty
                <li class="muted">{{ __('ui.nothing_here') }}</li>
            @endforelse
        </ol>
    </section>
</div>
@endsection
