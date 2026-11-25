@extends('layouts.app')
@section('title', $client->name)
@section('content')
<div class="page-head">
    <h1>{{ $client->name }} @unless($client->is_active)<span class="badge">{{ __('ui.inactive') }}</span>@endunless</h1>
    <div><a href="{{ route('admin.clients.edit', $client) }}" class="btn">{{ __('ui.edit') }}</a>
        <a href="{{ route('tickets.create', ['client' => $client->id]) }}" class="btn btn-primary">+ {{ __('ui.new_ticket') }}</a></div>
</div>
<div class="two-col">
    <section class="card">
        <h2>{{ __('ui.client_card') }}</h2>
        <dl class="dl">
            <dt>{{ __('ui.company_name') }}</dt><dd>{{ $client->company_name ?: '—' }}</dd>
            <dt>{{ __('ui.tax_id') }}</dt><dd>{{ $client->tax_id ?: '—' }}</dd>
            <dt>{{ __('ui.address') }}</dt><dd>{!! nl2br(e($client->address ?: '—')) !!}</dd>
            <dt>{{ __('ui.contact_email') }}</dt><dd>{{ $client->contact_email ?: '—' }}</dd>
            <dt>{{ __('ui.contact_phone') }}</dt><dd>{{ $client->contact_phone ?: '—' }}</dd>
            <dt>{{ __('ui.retention_months') }}</dt><dd>{{ $client->retention_months ?: '—' }}</dd>
        </dl>
        <p class="muted small">{{ __('ui.client_card_later') }}</p>
    </section>
    <section class="card">
        <h2>{{ __('ui.accounts') }}</h2>
        <ul class="plain">
        @foreach($client->users as $u)
            <li class="row-with-action">
                <span>{{ $u->name }} <span class="muted">{{ $u->email }}</span> @unless($u->is_active)<span class="badge">{{ __('ui.inactive') }}</span>@endunless</span>
                <form method="post" action="{{ route('admin.users.toggle', $u) }}">@csrf @method('patch')<button class="btn btn-small">{{ $u->is_active ? __('ui.deactivate') : __('ui.activate') }}</button></form>
            </li>
        @endforeach
        @foreach($client->invitations as $inv)
            <li class="muted">{{ $inv->email }} — {{ __('ui.invited_until', ['date' => $inv->expires_at->format('Y-m-d')]) }}</li>
        @endforeach
        </ul>
        <form method="post" action="{{ route('admin.clients.invite', $client) }}" class="stack">
            @csrf
            <h3>{{ __('ui.invite_person') }}</h3>
            <x-field name="name" :label="__('ui.name')" required />
            <x-field name="email" type="email" :label="__('ui.email')" required />
            <label class="field"><span>{{ __('ui.language') }}</span>
                <select name="locale">@foreach(config('agency.locales') as $l)<option value="{{ $l }}">{{ __('ui.lang_'.$l) }}</option>@endforeach</select>
            </label>
            <button class="btn">{{ __('ui.send_invitation') }}</button>
        </form>
    </section>
</div>
<section class="card">
    <h2>{{ __('ui.tickets') }}</h2>
    @forelse($tickets as $t) @include('tickets._row') @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
    <a href="{{ route('tickets.index', ['client' => $client->id, 'archive' => 1]) }}" class="small">{{ __('ui.see_all') }}</a>
</section>
@endsection
