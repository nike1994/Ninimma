@extends('layouts.app')
@section('title', __('social.contents'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ __('social.contents') }}</h1>
    <div class="head-actions"><a href="{{ route('social.contents.batch', array_filter(['client' => request('client')])) }}" class="btn"><x-icon name="layers" :size="16" />{{ __('social.batch_link') }}</a>
    <a href="{{ route('social.contents.create') }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('social.new_content') }}</a></div>
</div>
<section class="card">
    <form class="filters">
        <label class="search-field"><x-icon name="search" :size="18" /><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('social.search_contents') }}"></label>
        <select name="stage" onchange="this.form.submit()">
            <option value="">{{ __('social.all_stages') }}</option>
            @foreach(\App\Models\Content::STAGES as $s)<option value="{{ $s }}" @selected(request('stage') === $s)>{{ __('social.stage_'.$s) }}</option>@endforeach
        </select>
        <select name="client" onchange="this.form.submit()">
            <option value="">{{ __('ui.all_clients') }}</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>
    <div class="content-grid">
        @forelse($contents as $c)
            @php($thumb = $c->thumbnail())
            <a href="{{ route('social.contents.edit', $c) }}" class="content-card">
                <span class="content-img">@if($thumb)<img src="{{ route('files.show', [$thumb, $thumb->name]) }}" alt="" loading="lazy">@else<x-icon name="image" :size="30" />@endif
                    <span class="pill stage-{{ $c->stage }}">{{ __('social.stage_'.$c->stage) }}</span></span>
                <span class="content-body">
                    <strong>{{ $c->title }}</strong>
                    <small class="muted">{{ $c->client->name }} · {{ __('social.type_'.$c->type) }}</small>
                    <span class="content-pubs">
                        @foreach($c->publications as $p)
                            <span @class(['pub-mini', $p->status]) title="{{ $p->account->name }} · {{ $p->scheduled_at->format('d.m H:i') }}"><x-platform :platform="$p->platform" :size="18" />{{ $p->scheduled_at->format('d.m') }}</span>
                        @endforeach
                    </span>
                </span>
            </a>
        @empty
            <p class="muted">{{ __('ui.nothing_found') }}</p>
        @endforelse
    </div>
    <x-pager :paginator="$contents" />
</section>
@endsection
