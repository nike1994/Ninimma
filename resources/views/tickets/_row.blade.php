<a href="{{ route('tickets.show', $t) }}" class="row-link">
    <span class="row-main">
        @if($t->hasUnreadFor(auth()->user()))<span class="dot" title="{{ __('ui.new') }}"></span>@endif
        <span class="muted">#{{ $t->id }}</span> {{ $t->title }}
        @if(auth()->user()->isAdmin())<span class="muted">· {{ $t->client->name }}</span>@endif
    </span>
    <span class="row-meta">
        @if($t->isStalled())<span class="badge badge-warn">{{ __('ui.stalled') }}</span>@endif
        <x-status :ticket="$t" />
        <span class="muted small">{{ $t->last_activity_at?->diffForHumans() }}</span>
    </span>
</a>
