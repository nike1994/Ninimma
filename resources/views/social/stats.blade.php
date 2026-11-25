@extends('layouts.app')
@section('title', __('social.analytics'))
@section('content')
<h1 class="page-title">{{ __('social.stats_title') }}</h1>
<p class="muted">{{ __('social.stats_hint') }}</p>
<form method="post" action="{{ route('social.stats') }}" class="card">
    @csrf
    @if($queue->isEmpty())
        <p class="muted">{{ __('social.stats_empty') }}</p>
    @else
    <div class="table-scroll">
    <table class="table stats-table">
        <thead><tr><th>{{ __('social.publication') }}</th><th>{{ __('social.check') }}</th><th>{{ __('social.kpi_reach') }}</th><th>{{ __('social.reactions') }}</th><th>{{ __('social.comments') }}</th><th>{{ __('social.shares') }}</th><th>{{ __('social.kpi_clicks') }}</th></tr></thead>
        <tbody>
        @foreach($queue as $pub)
            <tr>
                <td><div class="acc"><x-platform :platform="$pub->platform" :size="24" />
                    <span><strong>{{ $pub->content->title }}</strong><small class="muted">{{ $pub->account->name }} · {{ $pub->published_at->format('d.m H:i') }}@if($pub->url) · <a href="{{ $pub->url }}" target="_blank" rel="noopener">{{ __('social.link') }}</a>@endif</small></span></div></td>
                <td><span class="pill">{{ __('social.after_days', ['n' => \App\Models\Publication::CHECK_DAYS[$pub->checks_done]]) }}</span></td>
                @foreach(['reach', 'reactions', 'comments', 'shares', 'clicks'] as $f)
                    <td><input type="number" min="0" name="m[{{ $pub->id }}][{{ $f }}]" placeholder="{{ $pub->latestMeasurement?->{$f} }}" class="num-input"></td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <button class="btn btn-send"><x-icon name="check" :size="18" />{{ __('social.save_stats') }}</button>
    @endif
</form>
@endsection
