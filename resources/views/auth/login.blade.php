@extends('layouts.guest')
@section('title', __('ui.login'))
@section('content')
<form method="post" action="{{ url('/login') }}" class="stack">
    @csrf
    <x-field name="email" type="email" :label="__('ui.email')" required autofocus autocomplete="username" />
    <x-field name="password" type="password" :label="__('ui.password')" required autocomplete="current-password" />
    <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('ui.remember_me') }}</label>
    <button class="btn btn-primary">{{ __('ui.login') }}</button>
    <a href="{{ route('password.request') }}" class="muted">{{ __('ui.forgot_password') }}</a>
</form>
@endsection
