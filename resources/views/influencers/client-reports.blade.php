@extends('layouts.app')
@section('title', __('inf.reports'))
@section('content')
<h1 class="page-title">{{ __('inf.reports') }}</h1>
<section class="card page-campaigns">
    @forelse($campaigns as $cmp)
        <a href="{{ route('reports.campaign', $cmp) }}" class="campaign-row">
            <span class="title-icon" style="width: 42px; height: 42px"><x-icon name="bar-chart" :size="18" /></span>
            <span class="campaign-name"><strong>{{ $cmp->name }}</strong><small class="muted">{{ $cmp->starts_on?->format('d.m') }}–{{ $cmp->ends_on?->format('d.m.Y') }}</small></span>
            <span class="pill cmp-{{ $cmp->status }}"><i></i>{{ __('social.cmp_'.$cmp->status) }}</span>
            <span></span><x-icon name="chevron-right" />
        </a>
    @empty
        <p class="muted">{{ __('ui.nothing_here') }}</p>
    @endforelse
</section>
<p><a href="{{ route('billing.index') }}" class="link-arrow">{{ __('ui.billing') }} <x-icon name="arrow-right" :size="16" /></a></p>
@endsection
