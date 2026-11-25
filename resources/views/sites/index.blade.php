@extends('layouts.app')
@section('title', __('site.title'))
@section('content')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="layers" :size="26" /></span>
        <div><h1>{{ __('site.title') }}</h1><p class="muted">{{ __('site.subtitle') }}</p></div></div>
    <div class="dash-head-right">
        <a href="{{ route('calendar') }}" class="btn"><x-icon name="calendar" :size="16" />{{ __('site.joint_calendar') }}</a>
        <a href="{{ route('sites.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('site.new') }}</a>
    </div>
</div>
<div class="grid-main">
    <section class="card">
        <form class="filters"><select name="client" onchange="this.form.submit()"><option value="">{{ __('ui.all_clients') }}</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach</select></form>
        @forelse($sites as $site)
            @php
    $next = $site->nextDeadline();
@endphp

            <a href="{{ route('sites.show', $site) }}" @class(['site-row', 'inactive' => ! $site->is_active])>
                <span class="site-icon platform-{{ $site->platform }}"><x-icon name="code" :size="20" /></span>
                <span class="site-main"><strong>{{ $site->domain }}</strong><small class="muted">{{ $site->client->name }} · {{ __('site.platform_'.$site->platform) }}@if($site->hosting) · {{ $site->hosting }}@endif</small></span>
                @if($site->care_hours)
                    @php
    $used = $site->minutesUsed(now());
    $pkg = (float) $site->care_hours * 60;
    $pct = $pkg ? min(100, (int) round($used / $pkg * 100)) : 0;
@endphp
                    <span class="site-pkg"><small class="muted">{{ __('site.package') }}</small><span @class(['limit-bar', 'warn' => $pct >= 80])><span style="width: {{ $pct }}%"></span></span><small>{{ \App\Models\TimeEntry::format($used) }} / {{ rtrim(rtrim((string) $site->care_hours, '0'), '.') }} h</small></span>
                @endif
                @if($next)<span @class(['pill', 'pill-pink' => $next->daysLeft() <= 14, 'pill-needs_decision' => $next->daysLeft() > 14 && $next->daysLeft() <= 30])>{{ __('site.kind_'.$next->kind) }} · {{ $next->due_on->format('d.m.Y') }}</span>@endif
            </a>
        @empty
            <p class="muted">{{ __('site.none') }}</p>
        @endforelse
    </section>
    <aside>
        <section class="card">
            <h2><x-icon name="calendar" /> {{ __('site.upcoming_deadlines') }}</h2>
            @forelse($deadlines as $d)
                <a href="{{ route('sites.show', $d->site) }}" class="deadline">
                    <span @class(['date-tile', 'tile-red' => $d->daysLeft() <= 14])><strong>{{ $d->due_on->format('j') }}</strong><small>{{ $d->due_on->translatedFormat('M') }}</small></span>
                    <span><strong>{{ __('site.kind_'.$d->kind) }}: {{ $d->name }}</strong><small class="muted">{{ $d->site->domain }} · {{ trans_choice('site.days_left', $d->daysLeft(), ['n' => $d->daysLeft()]) }}</small></span>
                </a>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </section>
        <a href="{{ route('checklists.index') }}" class="btn btn-block"><x-icon name="check-square" :size="16" />{{ __('site.checklists') }}</a>
    </aside>
</div>
@endsection
