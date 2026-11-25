<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
</head>
<body class="guest">
<x-waves variant="corner" />
<x-waves variant="sidebar" />
<div class="guest-box">
    <div class="guest-logo"><img src="{{ asset('img/logo.png') }}" alt=""><strong>{{ config('app.name') }}</strong></div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @yield('content')
    <div class="langs">
        @foreach(config('agency.locales') as $l)
            <a href="{{ route('locale', $l) }}" @class(['active' => app()->getLocale() === $l])>{{ strtoupper($l) }}</a>
        @endforeach
    </div>
</div>
</body>
</html>
