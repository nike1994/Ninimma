@extends('layouts.guest')
@section('title', __('ui.login'))
@section('content')
<div class="stack">
    <p>{{ __('ui.sso_no_account', ['email' => $email]) }}</p>
    <a href="{{ route('login') }}" class="btn btn-primary">{{ __('ui.login') }}</a>
</div>
@endsection
