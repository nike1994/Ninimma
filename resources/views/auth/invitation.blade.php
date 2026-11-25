@extends('layouts.guest')
@section('title', __('ui.activate_account'))
@section('content')
<form method="post" action="{{ route('invitation.accept', $token) }}" class="stack">
    @csrf
    <p>{{ __('ui.invitation_intro', ['email' => $invitation->email]) }}</p>
    <x-field name="name" :label="__('ui.name')" :value="$invitation->name" required />
    <x-field name="password" type="password" :label="__('ui.password')" required autocomplete="new-password" />
    <x-field name="password_confirmation" type="password" :label="__('ui.confirm_password')" required autocomplete="new-password" />
    @if($invitation->role === 'client')
        @php($terms = \App\Models\Setting::get('terms'))
        @if($terms)<div class="terms-box">{!! nl2br(e($terms)) !!}</div>@endif
        <label class="check"><input type="checkbox" name="terms" value="1" required> {{ __('ui.accept_terms') }}</label>
        @error('terms')<small class="error">{{ $message }}</small>@enderror
    @endif
    <button class="btn btn-primary">{{ __('ui.activate_account') }}</button>
</form>
@endsection
