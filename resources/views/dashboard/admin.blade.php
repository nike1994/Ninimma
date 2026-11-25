@extends('layouts.app')
@section('content')
<div class="greeting">
    <h1>{{ __('ui.hello', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ') ?: auth()->user()->name]) }} 👋</h1>
    <p class="muted">{{ __('ui.admin_intro') }}</p>
</div>

<div class="stats">
    <x-stat icon="play" :value="($byBall['agency'] ?? collect())->count()" :label="__('ui.ball_agency')" tone="blue" />
    <x-stat icon="clock" :value="($byBall['client'] ?? collect())->count()" :label="__('ui.ball_client')" tone="amber" />
    <x-stat icon="alert" :value="$stalled->count()" :label="__('ui.stalled')" :tone="$stalled->isNotEmpty() ? 'pink' : 'green'" />
    <x-stat icon="activity" :value="$lastCron ? \Illuminate\Support\Carbon::parse($lastCron)->diffForHumans(short: true) : '—'"
            :label="__('ui.last_cron').($recentErrors ? ' · '.trans_choice('ui.errors_count', $recentErrors, ['n' => $recentErrors]) : '')"
            :tone="$recentErrors || ! $lastCron ? 'pink' : 'teal'" :href="route('admin.system')" />
</div>

<div class="grid-main">
    <div>
        @if($stalled->isNotEmpty())
        <section class="card card-alert">
            <div class="card-head"><h2><x-icon name="alert" /> {{ __('ui.stalled') }}</h2></div>
            @foreach($stalled as $t)
                <div class="trow">
                    <x-type-icon :type="$t->type" />
                    <a href="{{ route('tickets.show', $t) }}" class="trow-main"><strong>{{ $t->title }}</strong><small class="muted">{{ $t->client->name }} · {{ __('ui.waiting_since', ['time' => $t->waiting_since?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}</small></a>
                    <form method="post" action="{{ route('admin.tickets.remind', $t) }}">@csrf<button class="btn btn-small"><x-icon name="bell" :size="15" /> {{ __('ui.remind') }}</button></form>
                </div>
            @endforeach
        </section>
        @endif
        <section class="card">
            <div class="card-head"><h2>{{ __('ui.ball_agency') }}</h2><a href="{{ route('tickets.index', ['status' => 'open']) }}" class="link-arrow">{{ __('ui.all_tickets') }} <x-icon name="arrow-right" :size="16" /></a></div>
            @forelse($byBall['agency'] ?? [] as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
        </section>
        <section class="card">
            <div class="card-head"><h2>{{ __('ui.ball_client') }}</h2></div>
            @forelse($byBall['client'] ?? [] as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
        </section>
    </div>
    <aside>
        <section class="card card-dark">
            <x-waves variant="card" />
            <h2>{{ __('ui.today') }}</h2>
            <dl class="dark-dl">
                <dt><x-icon name="message" :size="18" />{{ __('ui.unread_tickets') }}</dt><dd>{{ $unread->count() }}</dd>
                <dt><x-icon name="layers" :size="18" />{{ __('ui.open_tickets_all') }}</dt><dd>{{ $byBall->flatten()->count() }}</dd>
                <dt><x-icon name="users" :size="18" />{{ __('ui.active_clients') }}</dt><dd>{{ $clientsCount }}</dd>
            </dl>
        </section>
        <section class="card">
            <div class="card-head"><h2><x-icon name="zap" /> {{ __('ui.quick_actions') }}</h2></div>
            <div class="quick">
                <a href="{{ route('tickets.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('ui.new_ticket_short') }}</a>
                <a href="{{ route('admin.clients.create') }}" class="btn"><x-icon name="user-plus" :size="16" />{{ __('ui.new_client') }}</a>
                <a href="{{ route('admin.templates.index') }}" class="btn"><x-icon name="repeat" :size="16" />{{ __('ui.templates') }}</a>
            </div>
        </section>
        @if($unread->isNotEmpty())
        <section class="card">
            <div class="card-head"><h2>{{ __('ui.unread_tickets') }}</h2></div>
            @foreach($unread->take(6) as $t)
                <a href="{{ route('tickets.show', $t) }}" class="list-item list-item-sm"><x-avatar :name="$t->client->name" :size="32" />
                    <span class="list-main"><strong>{{ \Illuminate\Support\Str::limit($t->title, 40) }}</strong><small class="muted">{{ $t->client->name }}</small></span><span class="dot"></span></a>
            @endforeach
        </section>
        @endif
    </aside>
</div>
@endsection
