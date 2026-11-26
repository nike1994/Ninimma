{{-- Miniatura materiału: kadr z filmu (ładowany, gdy widoczny; podgląd po najechaniu) albo zdjęcie + kółko z profilem influencera. --}}
@props(['material', 'size' => 72, 'avatar' => true])
@php
    $file = $material->file;
    $stored = $file?->file;
    $src = $file ? route('files.show', [$file, $file->name]) : null;
    $inf = $material->collaboration?->influencer;
@endphp
<span {{ $attributes->merge(['class' => 'mat-thumb']) }} style="--s: {{ $size }}px" title="{{ $material->description }}">
    <span class="mat-thumb-media">
        @if($stored?->isVideo())
            <video data-src="{{ $src }}#t=0.8" preload="none" muted playsinline loop aria-label="{{ $material->description }}"></video>
            <span class="mat-thumb-play"><x-icon name="play" :size="12" /></span>
        @elseif($stored?->isImage())
            <img src="{{ $src }}" alt="" loading="lazy">
        @else
            <span class="mat-thumb-empty"><x-icon :name="$material->kind === 'photo' ? 'image' : 'video'" :size="(int) round($size / 3)" /></span>
        @endif
    </span>
    @if($avatar && $inf)
        <span class="mat-thumb-avatar">@include('influencers._photo', ['inf' => $inf, 'size' => max(20, (int) round($size * .38))])</span>
    @endif
</span>
