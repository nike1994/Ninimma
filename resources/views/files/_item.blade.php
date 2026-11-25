@php($f = $usage->file)
<div class="file-row">
    <span @class(['file-tile', 'ft-image' => $f->isImage(), 'ft-video' => $f->isVideo()])><x-icon :name="$f->isImage() ? 'image' : ($f->isVideo() ? 'video' : 'file-text')" :size="20" /></span>
    <span class="file-meta">
        <a href="{{ route('files.show', [$usage, $usage->name]) }}" target="_blank" class="file-name">{{ $usage->name }}</a>
        <small class="muted">{{ $usage->created_at->format('d.m, H:i') }}@isset($withTicket) · <a href="{{ route('tickets.show', $usage->usable->ticket) }}">#{{ $usage->usable->ticket_id }}</a>@endisset</small>
    </span>
    @if($f->location === 'hosting')
        <a href="{{ route('files.show', [$usage, $usage->name]) }}?download=1" class="icon-btn icon-btn-sm" title="{{ __('ui.download') }}"><x-icon name="download" :size="18" /></a>
    @endif
</div>
