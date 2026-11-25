@extends('layouts.app')
@section('content')
<h1>{{ __('ui.dashboard') }}</h1>
<div class="cards">
    <div class="card stat"><strong>{{ ($byBall['agency'] ?? collect())->count() }}</strong><span>{{ __('ui.ball_agency') }}</span></div>
    <div class="card stat"><strong>{{ ($byBall['client'] ?? collect())->count() }}</strong><span>{{ __('ui.ball_client') }}</span></div>
    <div class="card stat {{ $stalled->isNotEmpty() ? 'warn' : '' }}"><strong>{{ $stalled->count() }}</strong><span>{{ __('ui.stalled') }}</span></div>
    <div class="card stat {{ $recentErrors ? 'warn' : '' }}">
        <strong>{{ $lastCron ? \Illuminate\Support\Carbon::parse($lastCron)->diffForHumans() : '—' }}</strong>
        <span>{{ __('ui.last_cron') }} @if($recentErrors) · <a href="{{ route('admin.system') }}">{{ trans_choice('ui.errors_count', $recentErrors, ['n' => $recentErrors]) }}</a>@endif</span>
    </div>
</div>

@if($stalled->isNotEmpty())
<section class="card">
    <h2>{{ __('ui.stalled') }}</h2>
    @foreach($stalled as $t)
        <div class="row-with-action">
            @include('tickets._row')
            <form method="post" action="{{ route('admin.tickets.remind', $t) }}">@csrf<button class="btn btn-small">{{ __('ui.remind') }}</button></form>
        </div>
    @endforeach
</section>
@endif

<div class="two-col">
    <section class="card">
        <h2>{{ __('ui.ball_agency') }}</h2>
        @forelse(($byBall['agency'] ?? collect())->groupBy('client_id') as $group)
            <h3 class="muted">{{ $group->first()->client->name }}</h3>
            @foreach($group as $t) @include('tickets._row') @endforeach
        @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
    </section>
    <section class="card">
        <h2>{{ __('ui.ball_client') }}</h2>
        @forelse(($byBall['client'] ?? collect())->groupBy('client_id') as $group)
            <h3 class="muted">{{ $group->first()->client->name }}</h3>
            @foreach($group as $t) @include('tickets._row') @endforeach
        @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
    </section>
</div>
@endsection
