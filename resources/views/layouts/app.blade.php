<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.dashboard')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
@php($me = auth()->user())
<div class="shell">
    <input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><img src="{{ asset('img/logo.png') }}" alt=""> <span>{{ config('app.name') }}</span></a>
        <nav>
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>{{ __('ui.dashboard') }}</a>
            <a href="{{ route('tickets.index') }}" @class(['active' => request()->routeIs('tickets.*')])>{{ __('ui.tickets') }}</a>
            @if($me->isAdmin())
                <a href="{{ route('admin.clients.index') }}" @class(['active' => request()->routeIs('admin.clients.*')])>{{ __('ui.clients') }}</a>
                <a href="{{ route('admin.templates.index') }}" @class(['active' => request()->routeIs('admin.templates.*')])>{{ __('ui.templates') }}</a>
                <span class="nav-section">{{ __('ui.coming_soon') }}</span>
                <span class="nav-disabled" title="{{ __('ui.stage', ['n' => 3]) }}">{{ __('ui.publications') }}</span>
                <span class="nav-disabled" title="{{ __('ui.stage', ['n' => 4]) }}">{{ __('ui.influencers') }}</span>
                <span class="nav-disabled" title="{{ __('ui.stage', ['n' => 4]) }}">{{ __('ui.campaigns') }}</span>
                <span class="nav-disabled" title="{{ __('ui.stage', ['n' => 5]) }}">{{ __('ui.sites_seo') }}</span>
                <span class="nav-section">{{ __('ui.system') }}</span>
                <a href="{{ route('admin.team') }}" @class(['active' => request()->routeIs('admin.team')])>{{ __('ui.team') }}</a>
                <a href="{{ route('admin.system') }}" @class(['active' => request()->routeIs('admin.system')])>{{ __('ui.system_log') }}</a>
            @endif
        </nav>
        <div class="sidebar-foot">
            <a href="{{ route('settings') }}">{{ $me->name }}</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="link">{{ __('ui.logout') }}</button></form>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <label for="nav-toggle" class="burger" aria-label="Menu">☰</label>
            <form action="{{ route('tickets.index') }}" class="search">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_placeholder') }}">
            </form>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary">+ {{ __('ui.new_ticket') }}</a>
        </header>
        @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
        <div class="content">@yield('content')</div>
    </main>
</div>
@stack('scripts')
</body>
</html>
