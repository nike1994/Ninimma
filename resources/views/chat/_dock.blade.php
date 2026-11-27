{{-- Okno wiadomości: pływający przycisk + panel z boku (strona pod spodem zostaje dostępna — można kopiować i wklejać). --}}
@php
    $me = auth()->user();
    $full = $full ?? false;
    $i18n = ['title' => __('chat.title'), 'empty' => __('chat.empty'), 'copy' => __('chat.copy'), 'copied' => __('chat.copied'), 'error' => __('chat.error'),
        'readonly' => __('chat.readonly'), 'internal' => __('ui.internal_note'), 'no_messages' => __('chat.no_messages')];
@endphp
<div @class(['chat-dock', 'chat-full' => $full]) data-chat data-mode="{{ $full ? 'full' : 'dock' }}"
     data-threads-url="{{ route('chat.threads') }}" data-window-url="{{ route('chat.window') }}"
     data-i18n="{{ json_encode($i18n) }}">
    @unless($full)
        <button type="button" class="chat-fab" data-chat-toggle aria-label="{{ __('chat.title') }}"><x-icon name="message" :size="20" /><span class="hide-sm">{{ __('chat.title') }}</span>
            <b class="chat-badge" data-chat-badge @if(! ($unreadCount ?? 0)) hidden @endif>{{ $unreadCount ?? 0 }}</b></button>
    @endunless
    <section class="chat-panel" data-chat-panel @unless($full) hidden @endunless aria-label="{{ __('chat.title') }}">
        <header class="chat-head">
            <button type="button" class="chat-icon-btn" data-chat-back hidden aria-label="{{ __('chat.back') }}"><x-icon name="chevron-left" :size="18" /></button>
            <div class="chat-title"><strong data-chat-title>{{ __('chat.title') }}</strong><small class="muted" data-chat-sub></small></div>
            <a href="#" class="chat-icon-btn" data-chat-open-ticket hidden title="{{ __('chat.open_ticket') }}"><x-icon name="arrow-right" :size="17" /></a>
            @unless($full)
                <button type="button" class="chat-icon-btn" data-chat-popout title="{{ __('chat.popout') }}"><x-icon name="layers" :size="17" /></button>
                <button type="button" class="chat-icon-btn" data-chat-close aria-label="{{ __('chat.close') }}"><x-icon name="x" :size="18" /></button>
            @endunless
        </header>
        <div class="chat-list-view" data-chat-list-view>
            <div class="chat-tools">
                <input type="search" data-chat-q placeholder="{{ __('chat.search') }}" aria-label="{{ __('chat.search') }}">
                <nav class="chat-filters" data-chat-filters>
                    <button type="button" data-f="all" class="on">{{ __('chat.f_all') }}</button>
                    <button type="button" data-f="unread">{{ __('chat.f_unread') }}</button>
                    <button type="button" data-f="ours">{{ $me->isAdmin() ? __('chat.f_ours') : __('chat.f_mine') }}</button>
                </nav>
            </div>
            <div class="chat-threads" data-chat-threads><p class="muted small chat-pad">…</p></div>
        </div>
        <div class="chat-conv" data-chat-conv hidden>
            <div class="chat-msgs" data-chat-msgs></div>
            <form class="chat-reply" data-chat-reply>
                <textarea rows="2" maxlength="20000" placeholder="{{ __('chat.reply_ph') }}" data-chat-body></textarea>
                <div class="chat-reply-foot">
                    @if($me->isAdmin())<label class="check small"><input type="checkbox" data-chat-internal> {{ __('ui.internal_note') }}</label>@endif
                    <span class="muted small hide-sm">{{ __('chat.send_hint') }}</span>
                    <button class="btn btn-send btn-small">{{ __('ui.send') }}</button>
                </div>
            </form>
        </div>
    </section>
</div>
