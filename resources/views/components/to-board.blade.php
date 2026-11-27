{{-- Przycisk „Na tablicę” (tylko agencja, grafika lub film przypisany do klienta). --}}
@props(['usage', 'compact' => false])
@if($usage && auth()->user()?->isAdmin() && $usage->file && ($usage->file->isImage() || $usage->file->isVideo()))
    <button type="button" {{ $attributes->merge(['class' => 'btn btn-small to-board-btn'.($compact ? ' compact' : '')]) }} data-to-board="{{ route('to-board.targets', $usage) }}" title="{{ __('board.snap_title') }}">
        <x-icon name="edit" :size="14" />@unless($compact)<span>{{ __('board.snap_btn') }}</span>@endunless</button>
@endif
