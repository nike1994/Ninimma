@extends('layouts.guest')
@section('title', __('ui.forgot_password'))
@section('content')
<form method="post" action="{{ route('password.email') }}" class="stack">
    @csrf
    <p class="muted">{{ __('ui.forgot_password_hint') }}</p>
    <x-field name="email" type="email" :label="__('ui.email')" required autofocus />
    <button class="btn btn-primary">{{ __('ui.send_reset_link') }}</button>
    <a href="{{ route('login') }}" class="muted">{{ __('ui.back_to_login') }}</a>
</form>
@endsection
