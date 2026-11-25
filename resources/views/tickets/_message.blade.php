@php($mine = $message->user_id === $me->id)
<article id="m{{ $message->id }}" data-id="{{ $message->id }}" @class(['msg', 'msg-mine' => $mine, 'msg-internal' => $message->is_internal])>
    <header>
        <strong>{{ $message->user?->name ?? '—' }}</strong>
        @if($message->is_internal)<span class="badge badge-internal">{{ __('ui.internal_note') }}</span>@endif
        <time class="muted small" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('Y-m-d H:i') }}</time>
    </header>
    @if($message->body !== '')<div class="msg-body">{!! nl2br(e($message->body)) !!}</div>@endif
    @if($message->attachments->isNotEmpty())
        <div class="attachments">
            @foreach($message->attachments as $a)
                @php($url = route('files.show', [$a, $a->name]))
                @if($a->file->location !== 'hosting')
                    <span class="file muted">📦 {{ $a->name }}</span>
                @elseif($a->file->isImage())
                    <a href="{{ $url }}" target="_blank" class="thumb"><img src="{{ $url }}" alt="{{ $a->name }}" loading="lazy"></a>
                @elseif($a->file->isVideo())
                    <video src="{{ $url }}" controls preload="metadata" class="video"></video>
                @else
                    <a href="{{ $url }}?download=1" class="file">📄 {{ $a->name }}</a>
                @endif
            @endforeach
        </div>
    @endif
    @if($me->isAdmin() && ! $message->is_internal && $mine)<footer class="seen small muted" data-seen-for="{{ $message->id }}"></footer>@endif
</article>
