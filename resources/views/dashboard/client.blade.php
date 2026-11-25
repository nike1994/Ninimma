@extends('layouts.app')
@section('content')
<h1>{{ __('ui.hello', ['name' => auth()->user()->name]) }}</h1>
<section class="card">
    <h2>{{ __('ui.waiting_for_you') }}</h2>
    @forelse($waitingForMe as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_waiting') }}</p> @endforelse
</section>
<section class="card">
    <h2>{{ __('ui.in_progress') }}</h2>
    @forelse($inProgress as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
</section>
@if($recentlyDone->isNotEmpty())
<section class="card">
    <h2>{{ __('ui.recently_done') }}</h2>
    @foreach($recentlyDone as $t) @include('tickets._row') @endforeach
</section>
@endif
@endsection
