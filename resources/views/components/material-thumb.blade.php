{{-- Miniatura materiału: kadr z filmu (ładowany, gdy widoczny; podgląd po najechaniu) albo zdjęcie + kółko z profilem influencera.
     Dla klienta: tryb chroniony (znak wodny, odtwarzacz bez pobierania), a po wygaśnięciu licencji — tylko kłódka. --}}
@props(['material', 'size' => 72, 'avatar' => true, 'player' => false])
@php
    $me = auth()->user();
    $file = $material->file;
    $stored = $file?->file;
    $src = $file ? route('files.show', [$file, $file->name]) : null;
    $inf = $material->collaboration?->influencer;
    $locked = $file && $me && \App\Services\MediaProtection::isLockedFor($me, $file);
    $protected = $file && $me && ! $locked && \App\Services\MediaProtection::isProtectedFor($me, $file);
    $poster = $stored?->isVideo() && $stored->poster_id ? route('files.poster', $file) : null;
    // Brak kadru: zespół dorobi go przy pierwszym podglądzie (przeglądarka, w tle).
    $makePoster = $stored?->isVideo() && ! $stored->poster_id && $me?->isAdmin() ? route('files.poster.store', $file) : null;
    $mark = $protected ? mb_strtoupper(__('files.preview_mark')).' · '.($me->client?->name ?? $me->name) : null;
@endphp
<span {{ $attributes->merge(['class' => 'mat-thumb'.($protected ? ' is-protected' : '').($locked ? ' is-locked' : '')]) }} style="--s: {{ $size }}px" title="{{ $locked ? __('files.license_locked') : $material->description }}"
    @if($protected) oncontextmenu="return false" @endif
    @if($player && $stored?->isVideo() && ! $locked) data-player="{{ $src }}" role="button" tabindex="0" @endif>
    <span class="mat-thumb-media">
        @if($locked)
            <span class="mat-thumb-empty"><x-icon name="lock" :size="(int) round($size / 3)" /></span>
        @elseif($stored?->isVideo())
            @if($poster)<img src="{{ $poster }}" alt="" loading="lazy" draggable="false" class="mat-thumb-poster">@endif
            <video data-src="{{ $src }}#t=0.8" preload="none" muted playsinline loop disablepictureinpicture controlslist="nodownload" aria-label="{{ $material->description }}"
                @if($poster) data-hover-only @endif @if($makePoster) data-make-poster="{{ $makePoster }}" @endif></video>
            <span class="mat-thumb-play"><x-icon name="play" :size="12" /></span>
        @elseif($stored?->isImage())
            <img src="{{ $src }}" alt="" loading="lazy" draggable="false">
        @else
            <span class="mat-thumb-empty"><x-icon :name="$material->kind === 'photo' ? 'image' : 'video'" :size="(int) round($size / 3)" /></span>
        @endif
        @if($protected)<span class="mat-thumb-guard" data-mark="{{ $mark }}" aria-hidden="true"></span>@endif
    </span>
    @if($avatar && $inf)
        <span class="mat-thumb-avatar">@include('influencers._photo', ['inf' => $inf, 'size' => max(20, (int) round($size * .38))])</span>
    @endif
</span>
