@extends('layouts.app')
@section('title', __('site.client_title'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ __('site.client_title') }}</h1>
    @if($site)
    <div class="week-nav">
        <a href="{{ route('client.sites', ['site' => $site->id, 'month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="16" /></a>
        <strong class="month-label">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        <a href="{{ route('client.sites', ['site' => $site->id, 'month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-right" :size="16" /></a>
    </div>
    @endif
</div>
@if($sites->count() > 1)
    <div class="chips" style="margin-bottom: 1rem">@foreach($sites as $s)<a href="{{ route('client.sites', ['site' => $s->id]) }}" @class(['chip', 'chip-dark' => $site?->id === $s->id])>{{ $s->domain }}</a>@endforeach</div>
@endif
@if($site)
    <p class="muted"><strong>{{ $site->domain }}</strong> · {{ __('site.platform_'.$site->platform) }}</p>
    @include('sites._report')
@else
    <p class="card muted">{{ __('site.client_none') }}</p>
@endif
@endsection
