@extends('layouts.app')
@section('title', __('site.review').' '.$review->site->domain)
@section('content')
<div class="page-head">
    <div><h1 class="page-title" style="margin: 0">{{ __('site.review') }}: {{ $review->site->domain }}</h1>
        <p class="muted">{{ $review->due_on->translatedFormat('j F Y') }} · <a href="{{ route('sites.show', $review->site) }}">{{ __('site.back_to_site') }}</a>@if($review->ticket) · <a href="{{ route('tickets.show', $review->ticket) }}">#{{ $review->ticket_id }}</a>@endif</p></div>
    @if($review->completed_at)<span class="pill pill-done pill-lg">{{ __('site.completed') }} {{ $review->completed_at->format('d.m') }}</span>@endif
</div>
<div class="grid-main">
    <form method="post" action="{{ route('sites.reviews.update', $review) }}" class="card stack">
        @csrf @method('put')
        <h2><x-icon name="check-square" /> {{ __('site.checklist') }}</h2>
        @foreach($review->items as $i => $item)
            <label class="check-item"><input type="checkbox" name="checked[{{ $i }}]" value="1" @checked(! empty($review->checked[$i])) @disabled($review->completed_at)><span>{{ $item }}</span></label>
        @endforeach
        <x-field name="notes" type="textarea" rows="3" :label="__('site.review_notes')" :value="$review->notes" />
        @unless($review->completed_at)
            <div class="btn-row">
                <button class="btn">{{ __('ui.save') }}</button>
                <button class="btn btn-send" name="complete" value="1"><x-icon name="check" :size="16" /> {{ __('site.complete_review') }}</button>
            </div>
        @endunless
    </form>
    <aside>
        <form method="post" action="{{ route('sites.reviews.problem', $review) }}" class="card stack">
            @csrf
            <h2><x-icon name="alert" /> {{ __('site.found_problem') }}</h2>
            <p class="muted small">{{ __('site.problem_hint') }}</p>
            <input name="title" placeholder="{{ __('site.problem_placeholder') }}" required>
            <button class="btn btn-dark">{{ __('site.create_problem') }}</button>
        </form>
    </aside>
</div>
@endsection
