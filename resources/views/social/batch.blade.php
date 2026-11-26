@extends('layouts.app')
@section('title', __('social.batch_title'))
@section('content')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="layers" :size="26" /></span>
        <div><h1>{{ __('social.batch_title') }}</h1><p class="muted">{{ __('social.batch_subtitle') }}</p></div></div>
    <form method="get" class="inline-form">
        <select name="client" onchange="this.form.submit()" aria-label="{{ __('ui.client') }}">
            <option value="">{{ __('social.batch_pick_client') }}</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected($client?->id === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>
</div>

@if(! $client)
    <section class="card batch-empty"><x-icon name="users" :size="28" /><p>{{ __('social.batch_pick_hint') }}</p></section>
@else
@php
    $saved = collect($defaults['accounts'])->keyBy('id');
    $oldRows = old('rows');
    $oldFiles = $oldRows ? \App\Models\FileUsage::with('file')->whereIn('id', collect($oldRows)->pluck('uploads')->flatten()->filter())->where('uploaded_by', auth()->id())->get()->keyBy('id') : collect();
@endphp
<form method="post" action="{{ route('social.contents.batch.store') }}" class="batch" data-batch data-message-form
      data-start="{{ route('uploads.start') }}" data-poster="{{ route('files.poster.store', '__ID__') }}">
    @csrf
    <input type="hidden" name="client_id" value="{{ $client->id }}">

    <div class="batch-top">
        <section class="card stack">
            <h2><x-icon name="sliders" /> {{ __('social.batch_common') }}</h2>
            <div class="batch-accounts">
                @forelse($accounts as $a)
                    @php($on = old('accounts.'.$a->id.'.on', $saved->isEmpty() ? true : $saved->has($a->id)))
                    <label class="batch-acc">
                        <input type="checkbox" name="accounts[{{ $a->id }}][on]" value="1" @checked($on)>
                        <x-platform :platform="$a->platform" :size="22" />
                        <span><strong>{{ $a->platform->label() }}</strong><small class="muted">{{ $a->handle ?: $a->name }}</small></span>
                        <input type="time" name="accounts[{{ $a->id }}][time]" value="{{ old('accounts.'.$a->id.'.time', $saved[$a->id]['time'] ?? '12:00') }}" aria-label="{{ __('social.batch_time_for', ['platform' => $a->platform->label()]) }}">
                    </label>
                @empty
                    <p class="muted small">{{ __('social.no_accounts') }}</p>
                @endforelse
            </div>
            <div class="form-row form-row-3">
                <label class="field"><span>{{ __('ui.type') }}</span><select name="type">@foreach(\App\Models\Content::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $defaults['type']) === $t)>{{ __('social.type_'.$t) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('social.format') }}</span><select name="format">@foreach(\App\Models\Content::FORMATS as $f)<option value="{{ $f }}" @selected(old('format', $defaults['format']) === $f)>{{ __('social.format_'.$f) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('social.campaign') }}</span><select name="campaign_id"><option value="">—</option>@foreach($campaigns as $cmp)<option value="{{ $cmp->id }}" @selected(old('campaign_id') == $cmp->id)>{{ $cmp->name }}</option>@endforeach</select></label>
            </div>
            <label class="field"><span>{{ __('social.batch_hashtags') }}</span><input name="hashtags" value="{{ old('hashtags', $defaults['hashtags']) }}" placeholder="#marka #produkt" data-hashtags></label>
            @if($defaults['hashtag_sets'])
                <div class="tag-sets">@foreach($defaults['hashtag_sets'] as $set)<button type="button" class="chip chip-sm" data-tags="{{ $set['tags'] }}" title="{{ $set['tags'] }}">＃ {{ $set['name'] }}</button>@endforeach</div>
            @endif
            <details class="batch-sets"><summary class="small">{{ __('social.hashtag_sets_edit') }}</summary>
                <textarea name="hashtag_sets" rows="3" placeholder="{{ __('social.hashtag_sets_placeholder') }}">{{ old('hashtag_sets', \App\Http\Controllers\Social\BatchController::setsToText($defaults['hashtag_sets'])) }}</textarea></details>
            <div class="batch-flags">
                <label class="check"><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $defaults['visible_to_client']))> {{ __('social.visible_to_client') }}</label>
                @if($accounts->contains(fn ($a) => $a->isConnected()))<label class="check"><input type="checkbox" name="auto_publish" value="1" @checked(old('auto_publish', $defaults['auto_publish']))> <x-icon name="zap" :size="14" /> {{ __('api.auto_publish') }}</label>@endif
                <label class="check"><input type="checkbox" name="remember" value="1" checked> {{ __('social.batch_remember') }}</label>
            </div>
        </section>

        <section class="card stack batch-rhythm">
            <h2><x-icon name="calendar" /> {{ __('social.batch_rhythm') }}</h2>
            <p class="muted small">{{ __('social.batch_rhythm_hint') }}</p>
            <label class="field"><span>{{ __('social.batch_start') }}</span><input type="date" data-start-date value="{{ today()->addDay()->toDateString() }}"></label>
            <div class="batch-days" role="group" aria-label="{{ __('social.batch_days') }}">
                @foreach([1, 2, 3, 4, 5, 6, 0] as $d)
                    <label class="lic-ch-check"><input type="checkbox" data-weekday="{{ $d }}" @checked(in_array($d, [1, 3, 5], true))><span>{{ \Illuminate\Support\Carbon::now()->startOfWeek()->addDays(($d + 6) % 7)->translatedFormat('D') }}</span></label>
                @endforeach
            </div>
            <button type="button" class="btn btn-small btn-dark" data-fill-dates><x-icon name="calendar" :size="14" /> {{ __('social.batch_fill') }}</button>
        </section>
    </div>

    <section class="card stack">
        <div class="card-head"><h2><x-icon name="image" /> {{ __('social.batch_posts') }} <span class="pill" data-count>0</span></h2>
            <button type="button" class="btn btn-small" data-add-row><x-icon name="plus" :size="14" /> {{ __('social.batch_add_row') }}</button></div>
        <label class="batch-drop" data-drop>
            <input type="file" multiple accept="image/*,video/*" hidden data-files>
            <x-icon name="upload" :size="28" />
            <strong>{{ __('social.batch_drop') }}</strong>
            <small class="muted">{{ __('social.batch_drop_hint') }}</small>
        </label>
        @error('rows')<p class="error small">{{ $message }}</p>@enderror
        <div class="batch-rows" data-rows>
            @foreach($oldRows ?? [] as $i => $r)
                @include('social._batch-row', ['i' => $i, 'r' => $r, 'files' => collect($r['uploads'] ?? [])->map(fn ($id) => $oldFiles[$id] ?? null)->filter()])
            @endforeach
        </div>
        <template data-row-template>@include('social._batch-row', ['i' => '__I__', 'r' => [], 'files' => collect()])</template>
    </section>

    <div class="batch-bar">
        <span class="muted small" data-summary></span>
        <button class="btn btn-send"><x-icon name="check" :size="18" /> <span data-submit-label>{{ __('social.batch_submit') }}</span></button>
    </div>
</form>
@endif
@endsection
@push('scripts')
<script src="{{ asset('js/uploader.js') }}?v=4"></script>
<script src="{{ asset('js/batch.js') }}?v=2"></script>
@endpush
