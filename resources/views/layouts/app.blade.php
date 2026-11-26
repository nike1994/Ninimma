<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.dashboard')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
</head>
<body>
@php($me = auth()->user())
<x-waves variant="corner" />
<div class="shell">
    <input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><img src="{{ asset('img/logo.png') }}" alt=""> <span>{{ config('app.name') }}</span></a>
        @unless($me->isAdmin())<div class="sidebar-label">{{ __('ui.client_panel') }}</div>@endunless
        <nav class="nav">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])><x-icon name="home" />{{ __('ui.dashboard') }}</a>
            <a href="{{ route('tickets.index') }}" @class(['active' => request()->routeIs('tickets.*')])><x-icon name="folder" />{{ $me->isAdmin() ? __('ui.tickets') : __('ui.my_tickets') }}
                @if(! $me->isAdmin() && $unreadCount)<span class="nav-count">{{ $unreadCount }}</span>@endif</a>
            @if($me->isAdmin())
                <div @class(['nav-group', 'open' => request()->routeIs('social.*')])>
                    <a href="{{ route('social.dashboard') }}" @class(['nav-group-head', 'active-soft' => request()->routeIs('social.*')])><x-icon name="megaphone" />{{ __('social.title') }}<x-icon name="chevron-down" :size="16" class="nav-caret" /></a>
                    <div class="nav-sub">
                        <a href="{{ route('social.dashboard') }}" @class(['active' => request()->routeIs('social.dashboard')])>{{ __('social.overview') }}</a>
                        <a href="{{ route('social.calendar') }}" @class(['active' => request()->routeIs('social.calendar')])>{{ __('social.calendar') }}</a>
                        <a href="{{ route('social.contents.index') }}" @class(['active' => request()->routeIs('social.contents.*')])>{{ __('social.contents') }}</a>
                        <a href="{{ route('social.campaigns.index') }}" @class(['active' => request()->routeIs('social.campaigns.*')])>{{ __('social.campaigns_nav') }}</a>
                        <a href="{{ route('social.ads.index') }}" @class(['active' => request()->routeIs('social.ads.*')])>{{ __('ads.title_short') }}</a>
                        <a href="{{ route('social.stats') }}" @class(['active' => request()->routeIs('social.stats')])>{{ __('social.analytics') }}</a>
                        <a href="{{ route('social.accounts') }}" @class(['active' => request()->routeIs('social.accounts')])>{{ __('social.profiles') }}</a>
                    </div>
                </div>
                <a href="{{ route('influencers.index') }}" @class(['active' => request()->routeIs('influencers.*', 'collaborations.*', 'materials.*')])><x-icon name="users" />{{ __('ui.influencers') }}</a>
                @php($licSoon = \App\Models\Material::whereNotNull('license_until')->whereDate('license_until', '>=', today())->whereDate('license_until', '<=', today()->addDays(\App\Services\LicenseGuard::EXPIRING_DAYS))->count())
                <a href="{{ route('licenses.index') }}" @class(['active' => request()->routeIs('licenses.*')])><x-icon name="shield" />{{ __('lic.nav') }}@if($licSoon)<span class="nav-count nav-count-warn" title="{{ __('lic.kpi_expiring') }}">{{ $licSoon }}</span>@endif</a>
                <div @class(['nav-group', 'open' => request()->routeIs('sites.*', 'calendar', 'checklists.*')])>
                    <a href="{{ route('sites.index') }}" @class(['nav-group-head', 'active-soft' => request()->routeIs('sites.*', 'calendar', 'checklists.*')])><x-icon name="layers" />{{ __('ui.sites_seo') }}<x-icon name="chevron-down" :size="16" class="nav-caret" /></a>
                    <div class="nav-sub">
                        <a href="{{ route('sites.index') }}" @class(['active' => request()->routeIs('sites.index', 'sites.show', 'sites.edit', 'sites.create')])>{{ __('site.sites') }}</a>
                        <a href="{{ route('calendar') }}" @class(['active' => request()->routeIs('calendar')])>{{ __('site.joint_calendar') }}</a>
                        <a href="{{ route('checklists.index') }}" @class(['active' => request()->routeIs('checklists.*')])>{{ __('site.checklists') }}</a>
                    </div>
                </div>
                <a href="{{ route('admin.clients.index') }}" @class(['active' => request()->routeIs('admin.clients.*')])><x-icon name="building" />{{ __('ui.clients') }}</a>
            @else
                <a href="{{ route('files.index') }}" @class(['active' => request()->routeIs('files.index')])><x-icon name="file" />{{ __('ui.files') }}</a>
                <a href="{{ route('publications.index') }}" @class(['active' => request()->routeIs('publications.*')])><x-icon name="megaphone" />{{ __('ui.publications') }}</a>
                <a href="{{ route('client.sites') }}" @class(['active' => request()->routeIs('client.sites')])><x-icon name="layers" />{{ __('ui.sites_seo') }}</a>
                <a href="{{ route('materials.index') }}" @class(['active' => request()->routeIs('materials.*')])><x-icon name="image" />{{ __('inf.ready_materials') }}</a>
                <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])><x-icon name="bar-chart" />{{ __('inf.reports') }}</a>
                <a href="{{ route('billing.index') }}" @class(['active' => request()->routeIs('billing.*')])><x-icon name="grid" />{{ __('ui.billing') }}</a>
                <a href="{{ route('settings') }}" @class(['active' => request()->routeIs('settings')])><x-icon name="lock" />{{ __('ui.company_access') }}</a>
            @endif
        </nav>

        @if($me->isAdmin() && ! request()->routeIs('social.*', 'influencers.*', 'collaborations.*', 'sites.*', 'calendar', 'checklists.*'))
            <div class="sidebar-divider"></div>
            <div class="sidebar-label">{{ __('ui.ticket_list') }}</div>
            <div class="mini-list">
                @forelse($sidebarTickets as $st)
                    <a href="{{ route('tickets.show', $st) }}" @class(['mini', 'current' => request()->route('ticket')?->id === $st->id])>
                        <span class="mini-title">{{ $st->client->name }}<x-icon name="chevron-right" :size="16" /></span>
                        <span class="mini-sub">@if($st->hasUnreadFor($me))<span class="dot"></span>@endif{{ $st->title }}</span>
                        @if($st->isStalled())
                            <span class="pill pill-pink">{{ __('ui.stalled_for', ['n' => (int) $st->waiting_since?->diffInDays(now())]) }}</span>
                        @else
                            <span class="pill pill-{{ $st->status->value }}">{{ $st->status->label() }}</span>
                        @endif
                    </a>
                @empty
                    <p class="mini-empty">{{ __('ui.nothing_here') }}</p>
                @endforelse
            </div>
        @elseif(! $me->isAdmin())
            <div class="help-box">
                <span class="help-icon"><x-icon name="help" :size="22" /></span>
                <span>{{ __('ui.need_help') }}</span>
                <a href="{{ route('tickets.create') }}" class="btn btn-white">{{ __('ui.write_to_us') }} <x-icon name="arrow-right" :size="16" /></a>
            </div>
        @endif
        <x-waves variant="sidebar" />
    </aside>

    <main class="main">
        <header class="topbar">
            <label for="nav-toggle" class="burger" aria-label="Menu"><x-icon name="menu" /></label>
            @if($me->isAdmin())
                <form action="{{ route('tickets.index') }}" class="search">
                    <x-icon name="search" :size="18" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_placeholder') }}">
                </form>
            @else
                <div class="company">
                    <span class="company-logo"><x-icon name="building" :size="22" /></span>
                    <span><strong>{{ $me->client?->name }}</strong><small>{{ $me->client?->company_name }}</small></span>
                </div>
            @endif
            <div class="topbar-right">
                <a href="{{ route('tickets.create') }}" class="btn btn-primary"><x-icon name="plus" :size="18" /><span class="hide-sm">{{ __('ui.new_ticket') }}</span></a>
                @unless($me->isAdmin())
                    <a href="{{ route('tickets.index') }}" class="icon-btn" title="{{ __('ui.new_messages') }}"><x-icon name="bell" />@if($unreadCount)<span class="bell-dot"></span>@endif</a>
                @endunless
                <details class="user-chip">
                    <summary><x-avatar :name="$me->name" :size="36" /><span class="hide-sm"><small>{{ $me->isAdmin() ? __('ui.account_manager') : __('ui.account') }}</small><strong>{{ \Illuminate\Support\Str::before($me->name, ' ') ?: $me->name }}</strong></span><x-icon name="chevron-down" :size="16" /></summary>
                    <div class="user-menu">
                        <a href="{{ route('settings') }}"><x-icon name="sliders" :size="16" />{{ __('ui.settings') }}</a>
                        @if($me->isAdmin())
                            <a href="{{ route('billing.index') }}"><x-icon name="grid" :size="16" />{{ __('ui.billing') }}</a>
                            <a href="{{ route('admin.terms.edit') }}"><x-icon name="file-text" :size="16" />{{ __('ui.terms') }}</a>
                            <a href="{{ route('admin.templates.index') }}"><x-icon name="repeat" :size="16" />{{ __('ui.templates') }}</a>
                            <a href="{{ route('admin.team') }}"><x-icon name="shield" :size="16" />{{ __('ui.team') }}</a>
                            <a href="{{ route('admin.archive') }}"><x-icon name="download" :size="16" />{{ __('arch.title') }}</a>
                            <a href="{{ route('admin.system') }}"><x-icon name="activity" :size="16" />{{ __('ui.system_log') }}</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">@csrf<button><x-icon name="logout" :size="16" />{{ __('ui.logout') }}</button></form>
                    </div>
                </details>
            </div>
        </header>
        @if(session('status'))<div class="flash"><x-icon name="check-circle" :size="18" />{{ session('status') }}</div>@endif
        @if($errors->any())<div class="flash flash-error"><x-icon name="alert" :size="18" />{{ $errors->first() }}</div>@endif
        <div class="content">@yield('content')</div>
    </main>
</div>
<script src="{{ asset('js/media-thumbs.js') }}?v=3" defer></script>
@stack('scripts')
</body>
</html>
