@extends('layouts.app')
@section('title', __('social.campaigns'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ __('social.campaigns') }}</h1>
    <a href="{{ route('social.campaigns.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('social.new_campaign') }}</a>
</div>
<div class="chips" style="margin-bottom: 1rem">
    <a href="{{ route('social.campaigns.index') }}" @class(['chip', 'chip-dark' => ! request('status')])>{{ __('social.all') }}</a>
    @foreach(\App\Models\Campaign::STATUSES as $st)
        <a href="{{ route('social.campaigns.index', ['status' => $st]) }}" @class(['chip', 'chip-dark' => request('status') === $st])>{{ __('social.cmp_'.$st) }}</a>
    @endforeach
</div>
<section class="card page-campaigns">
    @forelse($campaigns as $cmp)
        @php($metric = $cmp->headlineMetric())
        <a href="{{ route('social.campaigns.edit', $cmp) }}" class="campaign-row">
            <x-avatar :name="$cmp->client->name" :size="42" />
            <span class="campaign-name"><strong>{{ $cmp->name }}</strong>
                <small class="muted">{{ $cmp->client->name }} · {{ $cmp->starts_on?->format('d.m') }}–{{ $cmp->ends_on?->format('d.m.Y') }} · {{ trans_choice('social.contents_count', $cmp->contents_count, ['n' => $cmp->contents_count]) }}</small></span>
            <span class="pill cmp-{{ $cmp->status }} hide-sm"><i></i>{{ __('social.cmp_'.$cmp->status) }}</span>
            <span class="campaign-num"><small>{{ __('social.budget_short') }}</small><strong>{{ number_format((float) $cmp->budget, 0, ',', ' ') }} zł</strong></span>
            <span class="campaign-num"><small>{{ $metric['label'] }}</small><strong>{{ $metric['value'] }}</strong></span>
        </a>
    @empty
        <p class="muted">{{ __('social.no_campaigns') }}</p>
    @endforelse
</section>
@endsection
