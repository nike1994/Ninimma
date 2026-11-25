@extends('boards._layout')
@section('body')
<header class="board-bar">
    <a href="{{ route('boards.show', $board) }}" class="icon-btn icon-btn-sm"><x-icon name="chevron-left" :size="18" /></a>
    <div class="board-title"><strong>{{ $board->name }} — {{ $version->description ?: ($version->is_auto ? __('ui.auto_version') : __('ui.version')) }}</strong>
        <small class="muted">{{ $version->user?->name }} · {{ $version->created_at->format('d.m.Y H:i') }} · {{ __('ui.preview_only') }}</small></div>
    @if($canEdit)
        <form method="post" action="{{ route('boards.versions.restore', [$board, $version]) }}" onsubmit="return confirm('{{ __('ui.confirm_restore') }}')">@csrf
            <button class="btn btn-small btn-dark"><x-icon name="history" :size="15" /> {{ __('ui.restore_version') }}</button></form>
    @endif
</header>
<div class="board-wrap"><div id="board" class="board-canvas"></div></div>
@php
    $boardConfig = [
        'lib' => asset('vendor/excalidraw/board.js'),
        'snapshot' => $version->elements,
        'sync' => route('boards.poll', $board),
        'side' => auth()->user()->isAdmin() ? 'admin' : 'client',
        'canEdit' => false,
        'lang' => app()->getLocale() === 'pl' ? 'pl-PL' : 'en',
        'labels' => [],
    ];
@endphp
<script>
    window.EXCALIDRAW_ASSET_PATH = @json(asset('vendor/excalidraw').'/');
    window.BOARD = {!! json_encode($boardConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
</script>
<script src="{{ asset('js/board-page.js') }}?v=1" type="module"></script>
@endsection
