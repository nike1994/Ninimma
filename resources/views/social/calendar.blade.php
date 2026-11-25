@extends('layouts.app')
@section('title', __('social.calendar'))
@section('content')
@php
    $me = auth()->user();
    $route = $me->isAdmin() ? 'social.calendar' : 'publications.calendar';
@endphp
<div class="page-head">
    <h1 class="page-title">{{ __('social.calendar') }}</h1>
    <div class="week-nav">
        <a href="{{ route($route, array_filter(['month' => $month->subMonth()->format('Y-m'), 'account' => $accountId])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="16" /></a>
        <strong class="month-label">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        <a href="{{ route($route, array_filter(['month' => $month->addMonth()->format('Y-m'), 'account' => $accountId])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-right" :size="16" /></a>
    </div>
    @if($me->isAdmin())
        <a href="{{ route('social.contents.create', ['stage' => 'preparing']) }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('social.new_post') }}</a>
    @endif
</div>
@if($accounts->isNotEmpty())
<div class="chips" style="margin-bottom: 1rem">
    <a href="{{ route($route, ['month' => $month->format('Y-m')]) }}" @class(['chip', 'chip-dark' => ! $accountId])>{{ __('social.all_accounts') }}</a>
    @foreach($accounts as $a)
        <a href="{{ route($route, ['month' => $month->format('Y-m'), 'account' => $a->id]) }}" @class(['chip', 'chip-dark' => $accountId === $a->id])><x-platform :platform="$a->platform" :size="18" />{{ $a->name }}</a>
    @endforeach
</div>
@endif
<section class="card month">
    @for($d = 0; $d < 7; $d++)<div class="month-dow">{{ $gridStart->addDays($d)->translatedFormat('D') }}</div>@endfor
    @for($day = $gridStart; $day <= $gridEnd; $day = $day->addDay())
        <div @class(['month-cell', 'other' => ! $day->isSameMonth($month), 'today' => $day->isToday()])>
            <span class="month-num">{{ $day->day }}</span>
            @foreach($pubs[$day->toDateString()] ?? [] as $pub)
                <a href="{{ $me->isAdmin() ? route('social.contents.edit', $pub->content_id) : route('publications.index').'#p'.$pub->id }}" @class(['month-item', $pub->status, 'lic-flagged' => isset($licFlags[$pub->id])]) @isset($licFlags[$pub->id]) title="{{ __('lic.calendar_flag') }}: {{ $licFlags[$pub->id]->implode(' ') }}" @endisset>
                    @isset($licFlags[$pub->id])<x-icon name="shield" :size="13" class="lic-flag-icon" />@endisset
                    <x-platform :platform="$pub->platform" :size="16" />
                    <span>{{ $pub->scheduled_at->format('H:i') }} {{ $pub->content->title }}</span>
                </a>
            @endforeach
        </div>
    @endfor
</section>
@endsection
