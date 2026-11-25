{{-- Okrągła plakietka platformy (uproszczone znaki, bez logotypów). --}}
@props(['platform', 'size' => 22])
@php
    $p = $platform instanceof \App\Enums\Platform ? $platform : \App\Enums\Platform::from($platform);
    $glyph = match ($p) {
        \App\Enums\Platform::Instagram => '<rect x="5" y="5" width="14" height="14" rx="4" fill="none" stroke="#fff" stroke-width="2"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="#fff" stroke-width="2"/><circle cx="16.3" cy="7.7" r="1" fill="#fff"/>',
        \App\Enums\Platform::Facebook => '<path d="M13.2 19v-6h2l.4-2.4h-2.4V9.2c0-.7.3-1.2 1.3-1.2h1.2V5.9c-.2 0-1-.1-1.9-.1-1.9 0-3.1 1.1-3.1 3.2v1.6H8.6V13h2.1v6z" fill="#fff"/>',
        \App\Enums\Platform::TikTok => '<path d="M14.5 5c.3 1.6 1.4 2.8 3 3v2.2c-1.1 0-2.1-.3-3-.9v4.6a4.2 4.2 0 1 1-4.2-4.2h.5v2.3a1.9 1.9 0 1 0 1.4 1.9V5z" fill="#fff"/>',
        \App\Enums\Platform::YouTube => '<path d="M10 8.5v7l6-3.5z" fill="#fff"/>',
        \App\Enums\Platform::LinkedIn => '<path d="M7 10h2.4v7H7zm1.2-3.6a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6zM11 10h2.3v1c.4-.6 1.1-1.2 2.3-1.2 2.3 0 2.7 1.5 2.7 3.5V17h-2.4v-3.3c0-.8 0-1.8-1.1-1.8s-1.3.9-1.3 1.8V17H11z" fill="#fff"/>',
        \App\Enums\Platform::Pinterest => '<path d="M12.3 5C8.4 5 6.5 7.7 6.5 10c0 1.4.5 2.6 1.6 3.1.2.1.3 0 .4-.2l.2-.6c0-.2 0-.3-.1-.5-.3-.4-.5-.9-.5-1.6 0-2.1 1.6-4 4.1-4 2.2 0 3.5 1.4 3.5 3.2 0 2.4-1.1 4.4-2.6 4.4-.9 0-1.5-.7-1.3-1.6.2-1 .7-2.2.7-2.9 0-.7-.4-1.2-1.1-1.2-.9 0-1.6.9-1.6 2.2 0 .8.3 1.3.3 1.3l-1.1 4.6c-.3 1.4 0 3 0 3.2 0 .1.1.1.2 0 .1-.1 1-1.3 1.3-2.5l.5-2c.3.5 1 .9 1.8.9 2.4 0 4-2.2 4-5.1C17.6 7.2 15.6 5 12.3 5z" fill="#fff"/>',
    };
    $bg = $p === \App\Enums\Platform::Instagram ? 'url(#ig-grad)' : $p->color();
@endphp
<svg {{ $attributes->merge(['class' => 'platform-badge']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" role="img" aria-label="{{ $p->label() }}">
    @if($p === \App\Enums\Platform::Instagram)
        <defs><linearGradient id="ig-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#feda75"/><stop offset=".35" stop-color="#fa7e1e"/><stop offset=".6" stop-color="#d62976"/><stop offset="1" stop-color="#4f5bd5"/></linearGradient></defs>
    @endif
    <circle cx="12" cy="12" r="12" fill="{{ $bg }}"/>{!! $glyph !!}
</svg>
