@extends('layouts.app')
@section('title', __('ui.terms'))
@section('content')
<h1 class="page-title">{{ __('ui.terms') }}</h1>
<section class="card narrow-lg stack">
    @if($terms)
        <div class="terms-box terms-box-lg">{!! nl2br(e($terms)) !!}</div>
    @else
        <p class="muted">{{ __('ui.terms_empty') }}</p>
    @endif
    @unless(auth()->user()->isAdmin())
        @if((int) auth()->user()->terms_version < $version)
            <form method="post" action="{{ route('terms.accept') }}" class="stack">
                @csrf
                <p class="muted">{{ __('ui.terms_new_version') }}</p>
                <label class="check"><input type="checkbox" name="accept" value="1" required> {{ __('ui.accept_terms') }}</label>
                <button class="btn btn-send">{{ __('ui.accept') }}</button>
            </form>
        @else
            <p class="muted small">{{ __('ui.terms_accepted_on', ['date' => auth()->user()->terms_accepted_at?->format('d.m.Y')]) }}</p>
        @endif
    @endunless
</section>
@endsection
