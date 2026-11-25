{{-- Wyceny: limit pracy, opcje, decyzja klienta; historia wersji. --}}
@php
    $me = auth()->user();
    $pending = $quotes->firstWhere('status', 'pending');
    $history = $quotes->where('status', '!=', 'pending')->sortByDesc('version');
@endphp
@if($ticket->handover_requested_at && ! $ticket->accepted_at)
    <div class="card handover">
        <x-icon name="check-circle" :size="26" />
        <div><strong>{{ __('ui.handover_title') }}</strong><p class="muted small">{{ __('ui.handover_hint') }}</p></div>
        @unless($me->isAdmin())
            <form method="post" action="{{ route('tickets.handover.accept', $ticket) }}">@csrf<button class="btn btn-send">{{ __('ui.confirm_handover') }}</button></form>
        @else
            <span class="pill pill-waiting_client">{{ __('ui.waiting_for_handover') }}</span>
        @endunless
    </div>
@elseif($ticket->accepted_at)
    <div class="card handover done"><x-icon name="check-circle" :size="22" /><span>{{ __('ui.handover_accepted_by', ['name' => $ticket->accepted_by ? \App\Models\User::find($ticket->accepted_by)?->name : '—', 'date' => $ticket->accepted_at->format('d.m.Y H:i')]) }}</span></div>
@endif

@if($pending)
    <section class="card quote quote-pending">
        <div class="card-head"><h2><x-icon name="file-text" /> {{ $pending->is_diagnosis ? __('ui.quote_diagnosis') : __('ui.quote') }} v{{ $pending->version }}</h2><span class="pill pill-needs_decision">{{ __('ui.awaiting_decision') }}</span></div>
        @if($pending->justification)<div class="msg-body">{!! nl2br(e($pending->justification)) !!}</div>@endif
        @if($pending->options)
            <div class="quote-options">
                @foreach($pending->options as $i => $opt)
                    <form method="post" action="{{ route('quotes.decide', $pending) }}" class="quote-option">
                        @csrf<input type="hidden" name="decision" value="accept"><input type="hidden" name="option" value="{{ $i }}">
                        <strong>{{ $opt['label'] }}</strong>
                        @if($opt['description'])<small class="muted">{{ $opt['description'] }}</small>@endif
                        <span class="quote-limit">@if($opt['hours'] !== null){{ __('ui.up_to_hours', ['h' => rtrim(rtrim(number_format($opt['hours'], 2, ',', ''), '0'), ',')]) }}@endif @if($opt['amount'] !== null) · {{ number_format($opt['amount'], 0, ',', ' ') }} zł @endif</span>
                        <button class="btn btn-small btn-dark">{{ __('ui.choose_option') }}</button>
                    </form>
                @endforeach
            </div>
        @else
            <p class="quote-limit-lg">
                @if($pending->hours_limit){{ __('ui.up_to_hours', ['h' => rtrim(rtrim(number_format((float) $pending->hours_limit, 2, ',', ''), '0'), ',')]) }}@endif
                @if($pending->amount) · {{ number_format((float) $pending->amount, 0, ',', ' ') }} zł @endif
            </p>
        @endif
        <p class="muted small">{{ __('ui.quote_rule') }}</p>
        <div class="btn-row">
            @unless($pending->options)
                <form method="post" action="{{ route('quotes.decide', $pending) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="btn btn-send btn-small"><x-icon name="check" :size="14" /> {{ __('ui.accept_quote') }}</button></form>
            @endunless
            <form method="post" action="{{ route('quotes.decide', $pending) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-small">{{ __('ui.reject_quote') }}</button></form>
        </div>
    </section>
@endif

@if($history->isNotEmpty())
    <details class="card quote-history">
        <summary><strong>{{ __('ui.quote_history') }}</strong> <span class="muted small">({{ $history->count() }})</span></summary>
        @foreach($history as $q)
            <div class="side-file">
                <span>v{{ $q->version }} · {{ $q->hours_limit ? __('ui.up_to_hours', ['h' => (float) $q->hours_limit]) : '' }}{{ $q->amount ? ' · '.number_format((float) $q->amount, 0, ',', ' ').' zł' : '' }}
                    @if($q->chosen_option !== null) · „{{ $q->options[$q->chosen_option]['label'] ?? '' }}”@endif</span>
                <span class="pill quote-{{ $q->status }}">{{ __('ui.quote_'.$q->status) }}</span>
                <small class="muted">{{ $q->decided_at?->format('d.m') ?? $q->created_at->format('d.m') }}</small>
            </div>
        @endforeach
    </details>
@endif

@if($me->isAdmin())
    <details class="card quote-form" @if($errors->has('options.*')) open @endif>
        <summary><x-icon name="plus" :size="16" /> <strong>{{ $quotes->isEmpty() ? __('ui.new_quote') : __('ui.scope_change') }}</strong></summary>
        <form method="post" action="{{ route('admin.tickets.quotes.store', $ticket) }}" class="stack">
            @csrf
            <div class="form-row">
                <x-field name="hours_limit" type="number" step="0.25" min="0" :label="__('ui.hours_limit')" />
                <x-field name="amount" type="number" step="0.01" min="0" :label="__('ui.amount_limit')" />
                <label class="check"><input type="checkbox" name="is_diagnosis" value="1"> {{ __('ui.paid_diagnosis') }}</label>
            </div>
            <x-field name="justification" type="textarea" rows="3" :label="__('ui.justification')" :placeholder="__('ui.justification_hint')" />
            <p class="muted small">{{ __('ui.options_hint') }}</p>
            @foreach([__('ui.opt_full'), __('ui.opt_workaround'), __('ui.opt_stop')] as $i => $placeholder)
                <div class="quote-opt-row">
                    <input name="options[{{ $i }}][label]" placeholder="{{ $placeholder }}">
                    <input name="options[{{ $i }}][hours]" type="number" step="0.25" min="0" placeholder="h">
                    <input name="options[{{ $i }}][amount]" type="number" step="0.01" min="0" placeholder="zł">
                    <input name="options[{{ $i }}][description]" placeholder="{{ __('ui.option_description') }}">
                </div>
            @endforeach
            <button class="btn btn-dark">{{ __('ui.send_quote') }}</button>
        </form>
    </details>
@endif
