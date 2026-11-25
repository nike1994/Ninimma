{{-- Miniatura treści z plakietką platformy. --}}
@php($thumb = $pub->content->thumbnail())
<span class="thumb-pub @if(!$thumb) no-img @endif">
    @if($thumb)<img src="{{ route('files.show', [$thumb, $thumb->name]) }}" alt="" loading="lazy">@else<x-icon :name="$pub->content->format === 'video' ? 'play' : 'image'" :size="18" />@endif
    <x-platform :platform="$pub->platform" :size="20" class="thumb-badge" />
    @if($pub->content->format === 'video' && $thumb)<span class="thumb-play"><x-icon name="play" :size="12" /></span>@endif
</span>
