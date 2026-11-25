@extends('layouts.app')
@section('title', __('ui.tickets'))
@section('content')
<h1>{{ __('ui.tickets') }}</h1>
<form class="filters">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_placeholder') }}">
    <select name="status">
        <option value="">{{ __('ui.all_statuses') }}</option>
        <option value="open" @selected(request('status') === 'open')>{{ __('ui.open') }}</option>
        @foreach(\App\Enums\TicketStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
    @if($clients->isNotEmpty())
        <select name="client">
            <option value="">{{ __('ui.all_clients') }}</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    @endif
    <label class="check"><input type="checkbox" name="archive" value="1" @checked(request('archive'))> {{ __('ui.include_archive') }}</label>
    <button class="btn">{{ __('ui.filter') }}</button>
</form>
<section class="card">
    @forelse($tickets as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_found') }}</p> @endforelse
</section>
<x-pager :paginator="$tickets" />
@endsection
