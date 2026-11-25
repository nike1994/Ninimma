@extends('layouts.app')
@section('title', __('ui.clients'))
@section('content')
<div class="page-head"><h1 class="page-title">{{ __('ui.clients') }}</h1><a href="{{ route('admin.clients.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('ui.new_client') }}</a></div>
<div class="client-grid">
    @forelse($clients as $c)
        <a href="{{ route('admin.clients.show', $c) }}" @class(['client-card', 'inactive' => ! $c->is_active])>
            <x-avatar :name="$c->name" :size="52" />
            <span class="client-card-body"><strong>{{ $c->name }}</strong><small class="muted">{{ $c->company_name ?: '—' }}</small></span>
            <span class="client-card-stats">
                <span><strong>{{ $c->open_tickets_count }}</strong>{{ __('ui.open_tickets') }}</span>
                <span><strong>{{ $c->tickets_count }}</strong>{{ __('ui.all_tickets_short') }}</span>
                <span><strong>{{ $c->users_count }}</strong>{{ __('ui.accounts') }}</span>
            </span>
            @unless($c->is_active)<span class="pill">{{ __('ui.inactive') }}</span>@endunless
        </a>
    @empty
        <p class="muted">{{ __('ui.nothing_here') }}</p>
    @endforelse
</div>
@endsection
