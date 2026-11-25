@php
    $mine = $message->user_id === $me->id;
    $fromAgency = $message->user?->isAdmin();
@endphp
<article id="m{{ $message->id }}" data-id="{{ $message->id }}" @class(['msg', 'msg-agency' => $fromAgency && ! $message->is_internal, 'msg-client' => ! $fromAgency, 'msg-internal' => $message->is_internal])>
    @if($message->is_internal)
        <div class="note"><x-icon name="lock" :size="18" />
            <div><strong>{{ __('ui.internal_note') }}</strong> <span class="muted">({{ __('ui.client_cant_see') }}) · {{ $message->user?->name }} · {{ $message->created_at->format('d.m H:i') }}</span>
                <div class="msg-body">{!! nl2br(e($message->body)) !!}</div></div>
        </div>
    @else
        @unless($fromAgency && $mine)
            <header><x-avatar :name="$message->user?->name ?? '?'" :size="30" /><strong>{{ $message->user?->name ?? '—' }}</strong></header>
        @endunless
        <div class="bubble">
            @if($message->body !== '')<div class="msg-body">{!! nl2br(e($message->body)) !!}</div>@endif
            <span class="bubble-meta">
                @if($me->isAdmin() && $fromAgency)<span class="seen" data-seen-for="{{ $message->id }}"></span>@endif
                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('H:i') }}</time>
            </span>
        </div>
    @endif
    @if($message->attachments->isNotEmpty())
        <div class="attachments">
            @foreach($message->attachments as $a)
                @php($url = route('files.show', [$a, $a->name]))
                @if($a->file->location !== 'hosting')
                    <span class="att-chip muted"><x-icon name="download" :size="16" />{{ $a->name }}</span>
                @elseif($a->file->isImage())
                    <a href="{{ $url }}" target="_blank" class="thumb"><img src="{{ $url }}" alt="{{ $a->name }}" loading="lazy"></a>
                @elseif($a->file->isVideo())
                    <details class="att-video"><summary class="att-chip"><span class="play"><x-icon name="play" :size="12" /></span>{{ $a->name }}</summary><video src="{{ $url }}" controls preload="none"></video></details>
                @else
                    <a href="{{ $url }}?download=1" class="att-chip"><x-icon name="file-text" :size="16" />{{ $a->name }}</a>
                @endif
            @endforeach
        </div>
    @endif
</article>
