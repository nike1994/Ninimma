@extends('boards._layout')
@section('body')
<header class="board-bar">
    <a href="{{ route('tickets.show', $board->ticket) }}" class="icon-btn icon-btn-sm" title="{{ __('ui.back_to_ticket') }}"><x-icon name="chevron-left" :size="18" /></a>
    <div class="board-title"><strong>{{ $board->name }}</strong><small class="muted">#{{ $board->ticket->id }} {{ $board->ticket->title }}</small></div>
    <div class="board-presence" data-presence title="{{ __('ui.board_presence') }}"></div>
    <span class="board-sync muted small" data-sync-state></span>
    @if($canEdit)
        <form class="board-save" data-save-version>
            <input name="description" placeholder="{{ __('ui.version_description') }}" maxlength="200">
            <button class="btn btn-small btn-dark"><x-icon name="check" :size="14" /> {{ __('ui.save_version') }}</button>
        </form>
    @endif
    <button type="button" class="btn btn-small" data-panel-toggle>
        <x-icon name="history" :size="15" /> {{ __('ui.versions_changes') }}
        @if($unseen)<span class="dot"></span>@endif
    </button>
</header>
<div class="board-wrap">
    <div id="board" class="board-canvas"></div>
    <aside class="board-panel" data-panel @if(! $unseen) hidden @endif>
        @if($changes['since'])
            <section>
                <h3>{{ __('ui.changes_since', ['date' => $changes['since']]) }}</h3>
                @foreach(['added' => 'plus', 'changed' => 'edit', 'removed' => 'x'] as $kind => $icon)
                    @foreach($changes[$kind] as $c)
                        <button type="button" class="change change-{{ $kind }}" data-goto="{{ $c['id'] }}" @disabled($kind === 'removed')>
                            <x-icon :name="$icon" :size="13" /><span>{{ __('ui.change_'.$kind) }}: {{ __('ui.el_'.$c['type']) }}@if($c['text']) „{{ $c['text'] }}”@endif</span>
                        </button>
                    @endforeach
                @endforeach
                @if(! array_filter([$changes['added'], $changes['changed'], $changes['removed']]))<p class="muted small">{{ __('ui.no_changes') }}</p>@endif
            </section>
        @endif
        @if($unseen)
            <button type="button" class="btn btn-small btn-send btn-block" data-mark-seen>{{ __('ui.mark_reviewed') }}</button>
        @endif
        <section>
            <h3>{{ __('ui.versions') }}</h3>
            @forelse($versions as $v)
                <a href="{{ route('boards.versions.show', [$board, $v]) }}" class="version-row">
                    <x-avatar :name="$v->user?->name ?? '?'" :size="26" />
                    <span><strong>{{ $v->description ?: ($v->is_auto ? __('ui.auto_version') : __('ui.version')) }}</strong>
                        <small class="muted">{{ $v->user?->name }} · {{ $v->created_at->format('d.m H:i') }}</small></span>
                </a>
            @empty
                <p class="muted small">{{ __('ui.no_versions') }}</p>
            @endforelse
        </section>
    </aside>
</div>
@php
    $boardConfig = [
        'lib' => asset('vendor/excalidraw/board.js'),
        'sync' => route('boards.poll', $board),
        'files' => route('boards.files.store', $board),
        'versions' => route('boards.versions.store', $board),
        'sessionEnd' => route('boards.session-end', $board),
        'seen' => route('boards.seen', $board),
        'side' => $side,
        'canEdit' => $canEdit,
        'lang' => app()->getLocale() === 'pl' ? 'pl-PL' : 'en',
        'labels' => ['saved' => __('ui.synced'), 'saving' => __('ui.syncing'), 'offline' => __('ui.offline'), 'viewOnly' => __('ui.view_only_mobile')],
    ];
@endphp
<script>
    window.EXCALIDRAW_ASSET_PATH = @json(asset('vendor/excalidraw').'/');
    window.BOARD = {!! json_encode($boardConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
</script>
<script src="{{ asset('js/board-page.js') }}?v=1" type="module"></script>
@endsection
