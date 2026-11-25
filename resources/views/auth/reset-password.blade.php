@extends('layouts.guest')
@section('title', __('ui.set_password'))
@section('content')
<form method="post" action="{{ route('password.update') }}" class="stack">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-field name="email" type="email" :label="__('ui.email')" :value="$email" required />
    <x-field name="password" type="password" :label="__('ui.new_password')" required autocomplete="new-password" />
    <x-field name="password_confirmation" type="password" :label="__('ui.confirm_password')" required autocomplete="new-password" />
    <button class="btn btn-primary">{{ __('ui.set_password') }}</button>
</form>
@endsection
