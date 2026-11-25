@extends('layouts.app')
@section('title', __('ui.clients'))
@section('content')
<div class="page-head"><h1>{{ __('ui.clients') }}</h1><a href="{{ route('admin.clients.create') }}" class="btn btn-primary">+ {{ __('ui.new_client') }}</a></div>
<section class="card">
    <table class="table">
        <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.open_tickets') }}</th><th>{{ __('ui.all_tickets') }}</th><th>{{ __('ui.accounts') }}</th></tr></thead>
        <tbody>
        @foreach($clients as $c)
            <tr @class(['inactive' => ! $c->is_active])>
                <td><a href="{{ route('admin.clients.show', $c) }}">{{ $c->name }}</a> @unless($c->is_active)<span class="badge">{{ __('ui.inactive') }}</span>@endunless</td>
                <td>{{ $c->open_tickets_count }}</td><td>{{ $c->tickets_count }}</td><td>{{ $c->users_count }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
