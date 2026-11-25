@extends('layouts.app')
@section('title', __('ui.tickets'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ auth()->user()->isAdmin() ? __('ui.tickets') : __('ui.my_tickets') }}</h1>
    <a href="{{ route('tickets.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('ui.new_ticket') }}</a>
</div>
<section class="card">
    <form class="filters">
        <label class="search-field"><x-icon name="search" :size="18" /><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_placeholder') }}"></label>
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ __('ui.all_statuses') }}</option>
            <option value="open" @selected(request('status') === 'open')>{{ __('ui.open') }}</option>
            @foreach(\App\Enums\TicketStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        @if($clients->isNotEmpty())
            <select name="client" onchange="this.form.submit()">
                <option value="">{{ __('ui.all_clients') }}</option>
                @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        @endif
        <label class="check"><input type="checkbox" name="archive" value="1" @checked(request('archive')) onchange="this.form.submit()"> {{ __('ui.include_archive') }}</label>
    </form>
    @forelse($tickets as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_found') }}</p> @endforelse
    <x-pager :paginator="$tickets" />
</section>
@endsection
