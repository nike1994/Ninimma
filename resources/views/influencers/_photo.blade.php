@if($inf->photo)
    <img src="{{ route('files.show', [$inf->photo, $inf->photo->name]) }}" alt="" class="inf-photo" style="--s: {{ $size ?? 56 }}px" loading="lazy">
@else
    <x-avatar :name="$inf->name" :size="$size ?? 56" />
@endif
