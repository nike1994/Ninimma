{{-- Jedna publikacja treści na koncie (wiersz formularza). $i = indeks, $p = Publication|null, $o = wpisane dane po nieudanym zapisie --}}
@php
    $o = $o ?? null;
    // Po nieudanym zapisie pokazujemy to, co było wpisane, a nie stan z bazy.
    $v = fn (string $k, $db) => $o !== null ? ($o[$k] ?? null) : $db;
    $on = fn (string $k, $db) => $o !== null ? ! empty($o[$k]) : (bool) $db;
    $rowErrors = is_int($i) ? collect($errors->get('pubs.'.$i.'.*'))->flatten() : collect();
@endphp
<div @class(['pub-row', 'pub-row-error' => $rowErrors->isNotEmpty()]) data-pub-row>
    <input type="hidden" name="pubs[{{ $i }}][id]" value="{{ $v('id', $p?->id) }}">
    @foreach($rowErrors->unique() as $msg)<div class="lic-issue lic-issue-error pub-row-msg"><x-icon name="lock" :size="16" /><span>{{ $msg }}</span></div>@endforeach
    <div class="pub-grid">
        <label class="field"><span>{{ __('social.account') }}</span>
            <select name="pubs[{{ $i }}][social_account_id]" data-account-select>
                <option value="">—</option>
                @foreach($accounts as $a)<option value="{{ $a->id }}" data-client="{{ $a->client_id }}" data-api="{{ $a->isConnected() ? 1 : 0 }}" @selected((string) $v('social_account_id', $p?->social_account_id) === (string) $a->id)>{{ $a->name }} · {{ $a->platform->label() }}</option>@endforeach
            </select>
        </label>
        <label class="field"><span>{{ __('social.date_time') }}</span><input type="datetime-local" name="pubs[{{ $i }}][scheduled_at]" value="{{ $v('scheduled_at', $p?->scheduled_at?->format('Y-m-d\TH:i')) }}"></label>
        <label class="field"><span>{{ __('social.status') }}</span>
            <select name="pubs[{{ $i }}][status]">@foreach(['planned', 'published', 'cancelled'] as $s)<option value="{{ $s }}" @selected(($v('status', $p?->status) ?? 'planned') === $s)>{{ __('social.pub_'.$s) }}</option>@endforeach</select>
        </label>
        <label class="field"><span>{{ __('social.link') }}</span><input type="url" name="pubs[{{ $i }}][url]" value="{{ $v('url', $p?->url) }}" placeholder="https://"></label>
    </div>
    <details class="pub-more" @if($v('caption', $p?->caption) || $on('promoted', $p?->promoted)) open @endif>
        <summary class="muted small">{{ __('social.more_options') }}</summary>
        <label class="field"><span>{{ __('social.caption_override') }}</span><textarea name="pubs[{{ $i }}][caption]" rows="2">{{ $v('caption', $p?->caption) }}</textarea></label>
        <div class="pub-grid">
            <label class="check"><input type="checkbox" name="pubs[{{ $i }}][promoted]" value="1" @checked($on('promoted', $p?->promoted))> {{ __('social.promoted') }}</label>
            <label class="field"><span>{{ __('social.budget') }}</span><input type="number" step="0.01" min="0" name="pubs[{{ $i }}][promo_budget]" value="{{ $v('promo_budget', $p?->promo_budget) }}"></label>
            <label class="field"><span>{{ __('ads.promo_from') }}</span><input type="date" name="pubs[{{ $i }}][promo_from]" value="{{ $v('promo_from', $p?->promo_from?->toDateString()) }}"></label>
            <label class="field"><span>{{ __('ads.promo_to') }}</span><input type="date" name="pubs[{{ $i }}][promo_to]" value="{{ $v('promo_to', $p?->promo_to?->toDateString()) }}"></label>
        </div>
        @if($p?->ad)<a href="{{ route('social.ads.edit', $p->ad) }}" class="small"><x-icon name="bar-chart" :size="14" /> {{ __('ads.open_results') }}</a>@endif
    </details>
    <div class="pub-auto" data-pub-auto>
        <label class="check"><input type="checkbox" name="pubs[{{ $i }}][auto_publish]" value="1" @checked($on('auto_publish', $p ? $p->auto_publish : true))> <x-icon name="zap" :size="14" /> {{ __('api.auto_publish') }}</label>
        @if($p)@include('social.contents._api-status', ['p' => $p])@endif
    </div>
    <div class="pub-foot">
        <label class="check"><input type="checkbox" name="pubs[{{ $i }}][visible_to_client]" value="1" @checked($on('visible_to_client', $p?->visible_to_client))> {{ __('social.visible_to_client') }}</label>
        @if($p?->client_status)<span @class(['pill', 'pill-done' => $p->client_status === 'approved', 'pill-pink' => $p->client_status === 'changes'])>{{ __('social.client_'.$p->client_status) }}</span>@endif
        @if($p?->client_comment)<span class="small muted">„{{ $p->client_comment }}”</span>@endif
        @if($p)<label class="check remove"><input type="checkbox" name="pubs[{{ $i }}][remove]" value="1" @checked($on('remove', false))> {{ __('social.remove') }}</label>@endif
    </div>
    @if($p?->measurements?->isNotEmpty())
        @php($m = $p->measurements->sortByDesc('measured_on')->first())
        <div class="small muted">{{ __('social.latest_stats') }}:
            {{ __('social.kpi_reach') }} {{ $m->reach }} · ♥ {{ $m->reactions }} · 💬 {{ $m->comments }} · ↗ {{ $m->shares }} · {{ __('social.kpi_clicks') }} {{ $m->clicks }} ({{ $m->measured_on->format('d.m') }})</div>
    @endif
</div>
