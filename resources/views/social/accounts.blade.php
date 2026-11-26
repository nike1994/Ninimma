@extends('layouts.app')
@section('title', __('social.profiles'))
@section('content')
<h1 class="page-title">{{ __('social.profiles') }}</h1>
@if(session('error'))<div class="flash flash-error" style="margin-bottom: 1rem">{{ session('error') }}</div>@endif
<div class="grid-main">
    <section class="card">
        @forelse($accounts as $a)
            <div @class(['acc-row', 'inactive' => ! $a->is_active])>
                <div class="acc"><x-avatar :name="$a->name" :size="42" /><x-platform :platform="$a->platform" :size="18" class="acc-badge" />
                    <span><strong>{{ $a->name }}</strong><small class="muted">{{ $a->platform->label() }}@if($a->handle) · {{ '@'.ltrim($a->handle, '@') }}@endif · {{ $a->client->name }}</small></span></div>
                <span class="muted small">{{ trans_choice('social.pub_count', $a->publications_count, ['n' => $a->publications_count]) }}</span>
                @if(\App\Services\Social\Integrations::enabledFor($a->platform))
                    <span class="acc-api">
                        @if($a->isConnected())
                            <span class="api-status api-published"><x-icon name="zap" :size="14" /> {{ __('api.connected_as', ['name' => $a->api_meta['username'] ?? $a->api_meta['name'] ?? $a->name]) }}</span>
                            <form method="post" action="{{ route('social.connect.disconnect', $a) }}" onsubmit="return confirm('{{ __('api.disconnect') }}?')">@csrf @method('delete')<button class="btn btn-small">{{ __('api.disconnect') }}</button></form>
                        @else
                            @if($a->api_error)<small class="api-error" title="{{ $a->api_error }}">{{ __('api.reconnect_needed') }}</small>@endif
                            <a href="{{ route('social.connect', $a) }}" class="btn btn-small btn-dark"><x-icon name="link" :size="14" /> {{ $a->api_error ? __('api.reconnect') : __('api.connect') }}</a>
                        @endif
                    </span>
                @endif
                @if($a->url)<a href="{{ $a->url }}" target="_blank" rel="noopener" class="icon-btn icon-btn-sm"><x-icon name="link" :size="16" /></a>@endif
                <form method="post" action="{{ route('social.accounts.toggle', $a) }}">@csrf @method('patch')<button class="btn btn-small">{{ $a->is_active ? __('ui.deactivate') : __('ui.activate') }}</button></form>
            </div>
        @empty
            <p class="muted">{{ __('social.no_accounts') }}</p>
        @endforelse
    </section>
    <form method="post" action="{{ route('social.accounts') }}" class="card stack">
        @csrf
        <h2><x-icon name="plus" /> {{ __('social.add_account') }}</h2>
        <label class="field"><span>{{ __('ui.client') }}</span>
            <select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>{{ __('social.platform') }}</span>
            <select name="platform">@foreach(\App\Enums\Platform::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
        </label>
        <x-field name="name" :label="__('social.account_name')" required />
        <x-field name="handle" :label="__('social.handle')" placeholder="@profil" />
        <x-field name="url" type="url" :label="__('social.link')" placeholder="https://" />
        <button class="btn btn-dark">{{ __('ui.save') }}</button>
    </form>
</div>
@endsection
