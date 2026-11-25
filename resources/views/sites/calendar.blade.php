@extends('layouts.app')
@section('title', __('site.joint_calendar'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ __('site.joint_calendar') }}</h1>
    <div class="week-nav">
        <a href="{{ route('calendar', array_filter(['month' => $month->subMonth()->format('Y-m'), 'client' => $client, 'kinds' => $kinds])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="16" /></a>
        <strong class="month-label">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        <a href="{{ route('calendar', array_filter(['month' => $month->addMonth()->format('Y-m'), 'client' => $client, 'kinds' => $kinds])) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-right" :size="16" /></a>
    </div>
</div>
<form class="filters">
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <select name="client" onchange="this.form.submit()"><option value="">{{ __('ui.all_clients') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($client === $c->id)>{{ $c->name }}</option>@endforeach</select>
    @foreach(\App\Http\Controllers\Sites\JointCalendarController::KINDS as $k)
        <label class="chip cal-chip cal-{{ $k }}"><input type="checkbox" name="kinds[]" value="{{ $k }}" @checked(in_array($k, $kinds, true)) onchange="this.form.submit()"> {{ __('site.cal_'.$k) }}</label>
    @endforeach
</form>
<section class="card month">
    @for($d = 0; $d < 7; $d++)<div class="month-dow">{{ $gridStart->addDays($d)->translatedFormat('D') }}</div>@endfor
    @for($day = $gridStart; $day <= $gridEnd; $day = $day->addDay())
        <div @class(['month-cell', 'other' => ! $day->isSameMonth($month), 'today' => $day->isToday()])>
            <span class="month-num">{{ $day->day }}</span>
            @foreach($items[$day->toDateString()] ?? [] as $it)
                <a href="{{ $it['url'] }}" class="month-item cal-{{ $it['kind'] }} @if(! empty($it['done'])) published @endif" title="{{ $it['label'] }}">
                    @if(! empty($it['platform']))<x-platform :platform="$it['platform']" :size="14" />@endif<span>{{ $it['label'] }}</span></a>
            @endforeach
        </div>
    @endfor
</section>
@endsection
