@extends('layouts.app')
@section('title', __('ui.publications'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ __('ui.publications') }}</h1>
    <a href="{{ route('publications.calendar') }}" class="btn"><x-icon name="calendar" :size="16" />{{ __('social.calendar') }}</a>
</div>
<div class="client-pubs">
    @forelse($pubs as $pub)
        @php($thumb = $pub->content->thumbnail())
        <article class="card client-pub" id="p{{ $pub->id }}">
            <div class="client-pub-img">@if($thumb)<img src="{{ route('files.show', [$thumb, $thumb->name]) }}" alt="" loading="lazy">@else<x-icon name="image" :size="32" />@endif
                <x-platform :platform="$pub->platform" :size="28" class="thumb-badge" /></div>
            <div class="client-pub-body">
                <p class="muted small">{{ $pub->account->name }} · {{ $pub->scheduled_at->translatedFormat('l, j F, H:i') }} · <span class="pill {{ $pub->status === 'published' ? 'pill-done' : 'pill-with_agency' }}">{{ __('social.pub_'.$pub->status) }}</span></p>
                <h2>{{ $pub->content->title }}</h2>
                <div class="msg-body">{!! nl2br(e($pub->caption ?: $pub->content->description)) !!}</div>
                @if($pub->content->hashtags)<p class="hashtags">{{ $pub->content->hashtags }}</p>@endif
                @if($pub->latestMeasurement)
                    <p class="small muted">{{ __('social.kpi_reach') }}: <strong>{{ \App\Support\Num::short($pub->latestMeasurement->reach) }}</strong> · {{ __('social.kpi_engagement') }}: <strong>{{ $pub->engagement() }}</strong></p>
                @endif
                @if($pub->status === 'planned')
                    @if($pub->client_status === 'approved')
                        <span class="pill pill-done"><x-icon name="check" :size="14" /> {{ __('social.client_approved') }}</span>
                    @else
                        <form method="post" action="{{ route('publications.respond', $pub) }}" class="respond">
                            @csrf
                            <textarea name="comment" rows="2" placeholder="{{ __('social.comment_placeholder') }}">{{ $pub->client_comment }}</textarea>
                            <div class="btn-row">
                                <button name="decision" value="approved" class="btn btn-send btn-small"><x-icon name="thumbs-up" :size="15" />{{ __('social.approve') }}</button>
                                <button name="decision" value="changes" class="btn btn-small">{{ __('social.request_changes') }}</button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
        </article>
    @empty
        <p class="muted">{{ __('social.nothing_shared') }}</p>
    @endforelse
</div>
<x-pager :paginator="$pubs" />
@endsection
