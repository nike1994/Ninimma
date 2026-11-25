@extends('layouts.app')
@section('title', __('ui.settings'))
@section('content')
@php($me = auth()->user())
<h1 class="page-title">{{ __('ui.settings') }}</h1>
<div class="two-col">
<form method="post" action="{{ route('settings') }}" class="card stack">
    @csrf @method('put')
    <h2>{{ __('ui.profile') }}</h2>
    <x-field name="name" :label="__('ui.name')" :value="$me->name" required />
    <label class="field"><span>{{ __('ui.language') }}</span>
        <select name="locale">@foreach(config('agency.locales') as $l)<option value="{{ $l }}" @selected($me->locale === $l)>{{ __('ui.lang_'.$l) }}</option>@endforeach</select>
    </label>
    <label class="field"><span>{{ __('ui.notifications') }}</span>
        <select name="notify_mode">
            <option value="each" @selected($me->notify_mode === 'each')>{{ __('ui.notify_each') }}</option>
            <option value="daily" @selected($me->notify_mode === 'daily')>{{ __('ui.notify_daily') }}</option>
        </select>
    </label>
    <button class="btn btn-dark">{{ __('ui.save') }}</button>
</form>
<form method="post" action="{{ route('settings.password') }}" class="card stack">
    @csrf @method('put')
    <h2>{{ __('ui.change_password') }}</h2>
    <x-field name="current_password" type="password" :label="__('ui.current_password')" required autocomplete="current-password" />
    <x-field name="password" type="password" :label="__('ui.new_password')" required autocomplete="new-password" />
    <x-field name="password_confirmation" type="password" :label="__('ui.confirm_password')" required autocomplete="new-password" />
    <button class="btn">{{ __('ui.save') }}</button>
</form>
</div>
@if($colleagues->isNotEmpty())
<section class="card">
    <h2>{{ __('ui.company_people') }}</h2>
    <ul>@foreach($colleagues as $c)<li>{{ $c->name }} <span class="muted">{{ $c->email }}</span></li>@endforeach</ul>
    <p class="muted small">{{ __('ui.company_people_hint') }}</p>
</section>
@endif
@endsection
