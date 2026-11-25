@extends('layouts.app')
@section('title', __('inf.library'))
@section('content')
@php($me = auth()->user())
<div class="page-head"><h1 class="page-title">{{ $me->isAdmin() ? __('inf.library') : __('inf.ready_materials') }}</h1></div>
<div class="chips" style="margin-bottom: 1rem">
    <a href="{{ route('materials.index') }}" @class(['chip', 'chip-dark' => ! request('license')])>{{ __('social.all') }}</a>
    <a href="{{ route('materials.index', ['license' => 'soon']) }}" @class(['chip', 'chip-dark' => request('license') === 'soon'])>{{ __('inf.license_soon') }}</a>
    <a href="{{ route('materials.index', ['license' => 'expired']) }}" @class(['chip', 'chip-dark' => request('license') === 'expired'])>{{ __('inf.license_expired') }}</a>
</div>
<div class="content-grid">
    @forelse($materials as $m)
        <div class="content-card">
            <span class="content-img">
                @if($m->file && $m->file->file->isImage())<img src="{{ route('files.show', [$m->file, $m->file->name]) }}" alt="" loading="lazy">@else<x-icon :name="$m->file ? 'video' : 'image'" :size="30" />@endif
                @if($m->licenseExpired())<span class="pill pill-pink">{{ __('inf.license_expired') }}</span>@elseif($m->licenseExpiringSoon())<span class="pill pill-needs_decision">{{ __('inf.license_soon') }}</span>@elseif(! $m->is_received)<span class="pill">{{ __('inf.awaiting') }}</span>@endif
            </span>
            <span class="content-body">
                <strong>{{ $m->description }}</strong>
                <small class="muted">{{ $m->collaboration->campaign->name }}@if($me->isAdmin()) · {{ $m->collaboration->influencer->name }}@endif</small>
                <small>{{ __('inf.license_until') }}: <strong>{{ $m->license_until?->format('d.m.Y') ?? '—' }}</strong></small>
                @if($m->publication)<small class="muted"><x-platform :platform="$m->publication->platform" :size="14" /> {{ $m->publication->scheduled_at->format('d.m') }}</small>@endif
                @if($m->file)<a href="{{ route('files.show', [$m->file, $m->file->name]) }}?download=1" class="small"><x-icon name="download" :size="14" /> {{ __('ui.download') }}</a>@endif
                @if($me->isAdmin())<a href="{{ route('collaborations.show', $m->collaboration) }}" class="small">{{ __('inf.open_collab') }}</a>@endif
            </span>
        </div>
    @empty
        <p class="muted">{{ __('ui.nothing_here') }}</p>
    @endforelse
</div>
<x-pager :paginator="$materials" />
@endsection
