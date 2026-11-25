@extends('layouts.app')
@section('title', __('ui.team'))
@section('content')
<h1>{{ __('ui.team') }}</h1>
<div class="two-col">
<section class="card">
    <ul class="plain">
        @foreach($admins as $a)
            <li>{{ $a->name }} <span class="muted">{{ $a->email }}</span>
                @if($a->hasTwoFactor())<span class="badge">2FA</span>@else<span class="badge badge-warn">{{ __('ui.no_2fa') }}</span>@endif</li>
        @endforeach
        @foreach($pending as $inv)<li class="muted">{{ $inv->email }} — {{ __('ui.invited_until', ['date' => $inv->expires_at->format('Y-m-d')]) }}</li>@endforeach
    </ul>
</section>
<form method="post" action="{{ route('admin.team') }}" class="card stack">
    @csrf
    <h2>{{ __('ui.invite_admin') }}</h2>
    <x-field name="name" :label="__('ui.name')" required />
    <x-field name="email" type="email" :label="__('ui.email')" required />
    <label class="field"><span>{{ __('ui.language') }}</span>
        <select name="locale">@foreach(config('agency.locales') as $l)<option value="{{ $l }}">{{ __('ui.lang_'.$l) }}</option>@endforeach</select>
    </label>
    <button class="btn">{{ __('ui.send_invitation') }}</button>
</form>
</div>
@endsection
