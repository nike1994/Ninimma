@extends('layouts.guest')
@section('title', __('ui.two_factor'))
@section('content')
<form method="post" action="{{ url('/two-factor') }}" class="stack">
    @csrf
    <p class="muted">{{ __('ui.two_factor_hint') }}</p>
    <x-field name="code" :label="__('ui.code')" required autofocus autocomplete="one-time-code" inputmode="numeric" />
    <button class="btn btn-primary">{{ __('ui.verify') }}</button>
</form>
<form method="post" action="{{ route('logout') }}">@csrf<button class="link muted">{{ __('ui.logout') }}</button></form>
@endsection
