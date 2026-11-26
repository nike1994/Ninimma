@extends('layouts.app')
@section('title', $content->exists ? $content->title : __('social.new_content'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ $content->exists ? $content->title : __('social.new_content') }}</h1>
    @if($content->exists)
        <div class="head-actions">
        <form method="post" action="{{ route('social.contents.duplicate', $content) }}">@csrf<button class="btn btn-small"><x-icon name="layers" :size="14" /> {{ __('social.duplicate') }}</button></form>
        <form method="post" action="{{ route('social.contents.destroy', $content) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('delete')<button class="btn btn-small">{{ __('social.delete') }}</button></form>
        </div>
    @endif
</div>
@php
    $previewAccounts = $accounts->mapWithKeys(fn ($a) => [$a->id => ['name' => $a->api_meta['name'] ?? $a->name, 'handle' => ltrim((string) ($a->api_meta['username'] ?? $a->handle ?: $a->name), '@'),
        'platform' => $a->platform->value, 'label' => $a->platform->label(), 'avatar' => $a->avatarUrl()]]);
    $previewFiles = $content->exists ? $content->files->filter(fn ($f) => $f->file)->map(fn ($f) => [
        'url' => $f->file->isImage() ? route('files.show', [$f, $f->name]) : ($f->file->poster_id ? route('files.poster', $f) : null),
        'video' => $f->file->isVideo(),
    ])->values() : collect();
@endphp
<form method="post" action="{{ $content->exists ? route('social.contents.update', $content) : route('social.contents.store') }}" class="content-form" data-message-form
      data-preview-accounts="{{ json_encode($previewAccounts) }}" data-limits="{{ json_encode(\App\Support\PlatformLimits::LIMITS) }}" data-files="{{ json_encode($previewFiles) }}">
    @csrf @if($content->exists) @method('put') @endif
    <section class="card stack">
        <h2><x-icon name="file-text" /> {{ __('social.content') }}</h2>
        <div class="form-row">
            <label class="field"><span>{{ __('ui.client') }}</span>
                <select name="client_id" required data-client-select>
                    <option value=""></option>
                    @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $content->client_id) == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </label>
            <label class="field"><span>{{ __('social.stage') }}</span>
                <select name="stage">@foreach(\App\Models\Content::STAGES as $s)<option value="{{ $s }}" @selected(old('stage', $content->stage) === $s)>{{ __('social.stage_'.$s) }}</option>@endforeach</select>
            </label>
            <div class="form-row form-row-2">
                <label class="field"><span>{{ __('ui.type') }}</span>
                    <select name="type">@foreach(\App\Models\Content::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $content->type) === $t)>{{ __('social.type_'.$t) }}</option>@endforeach</select>
                </label>
                <label class="field"><span>{{ __('social.format') }}</span>
                    <select name="format">@foreach(\App\Models\Content::FORMATS as $f)<option value="{{ $f }}" @selected(old('format', $content->format) === $f)>{{ __('social.format_'.$f) }}</option>@endforeach</select>
                </label>
            </div>
        </div>
        <x-field name="title" :label="__('ui.title')" :value="$content->title" required maxlength="200" />
        <label class="field"><span>{{ __('social.campaign') }}</span>
            <select name="campaign_id" data-campaign-select>
                <option value="">—</option>
                @foreach($campaigns as $cmp)<option value="{{ $cmp->id }}" data-client="{{ $cmp->client_id }}" @selected(old('campaign_id', $content->campaign_id) == $cmp->id)>{{ $cmp->name }}</option>@endforeach
            </select>
        </label>
        <x-field name="description" type="textarea" :label="__('ui.description')" :value="$content->description" rows="5" data-paste-target data-desc />
        <div class="text-counter" data-counter="main" aria-live="polite"></div>
        <x-field name="hashtags" :label="__('social.hashtags')" :value="$content->hashtags" placeholder="#marka #produkt" data-hashtags />
        @if($hashtagSets->isNotEmpty())
            <div class="tag-sets" data-tag-sets>
                <small class="muted">{{ __('social.hashtag_sets') }}:</small>
                @foreach($hashtagSets as $clientId => $sets)
                    @foreach($sets as $set)<button type="button" class="chip chip-sm" data-client="{{ $clientId }}" data-tags="{{ $set['tags'] }}" title="{{ $set['tags'] }}">＃ {{ $set['name'] }}</button>@endforeach
                @endforeach
            </div>
        @endif
        @if($content->exists && $content->files->isNotEmpty())
            <div class="content-files">
                @foreach($content->files as $f)
                    <a href="{{ route('files.show', [$f, $f->name]) }}" target="_blank" class="content-file">
                        @if($f->file->isImage())<img src="{{ route('files.show', [$f, $f->name]) }}" alt="">@else<x-icon :name="$f->file->isVideo() ? 'video' : 'file'" :size="24" />@endif
                        <small>{{ $f->name }}</small></a>
                @endforeach
            </div>
        @endif
        @include('tickets._uploader', ['noSend' => true])
    </section>

    <section class="card stack">
        <h2><x-icon name="shield" /> {{ __('lic.materials') }}</h2>
        <p class="muted small">{{ __('lic.materials_hint') }}</p>
        @include('licenses._issues', ['issues' => $licenseIssues])
        @include('licenses._picker', ['name' => 'material_ids[]', 'selected' => old('material_ids', $selectedMaterials), 'multiple' => true])
    </section>

    <section class="card stack">
        <h2><x-icon name="share" /> {{ __('social.publications') }}</h2>
        <p class="muted small">{{ __('social.publications_hint') }}</p>
        <div data-pubs>
            @php($pubs = $content->exists ? $content->publications()->with('measurements')->orderBy('scheduled_at')->get() : collect())
            @if(is_array(old('pubs')))
                {{-- Nieudany zapis (np. blokada licencji): wiersze z wpisanymi danymi, nic nie przepada. --}}
                @foreach(old('pubs') as $i => $row)
                    @include('social.contents._pub', ['i' => $i, 'p' => $pubs->firstWhere('id', (int) ($row['id'] ?? 0)), 'o' => $row])
                @endforeach
            @else
                @foreach($pubs as $i => $p) @include('social.contents._pub', ['i' => $i, 'p' => $p]) @endforeach
                @if(! empty($defaultRows))
                    {{-- Zwykłe konta i godziny klienta (ustawienia domyślne z planowania seryjnego). --}}
                    @foreach($defaultRows as $i => $row) @include('social.contents._pub', ['i' => $i, 'p' => null, 'o' => $row]) @endforeach
                @else
                    @include('social.contents._pub', ['i' => $pubs->count(), 'p' => null, 'o' => $prefill ?? null])
                @endif
            @endif
        </div>
        <template data-pub-template>@include('social.contents._pub', ['i' => '__I__', 'p' => null])</template>
        <button type="button" class="btn btn-small" data-add-pub><x-icon name="plus" :size="14" /> {{ __('social.add_publication') }}</button>
    </section>
    <button class="btn btn-send btn-block"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>

    <aside class="card post-preview" data-preview aria-label="{{ __('social.preview') }}">
        <h2><x-icon name="eye" /> {{ __('social.preview') }}</h2>
        <div class="preview-tabs" data-preview-tabs role="tablist"></div>
        <div class="preview-stage" data-preview-stage></div>
        <p class="muted small" data-preview-note></p>
    </aside>
</form>
@endsection
@push('scripts')
<script src="{{ asset('js/uploader.js') }}?v=5"></script>
<script src="{{ asset('js/post-preview.js') }}?v=6"></script>
<script>
(function () {
    const wrap = document.querySelector('[data-pubs]'), tpl = document.querySelector('[data-pub-template]');
    // Kolejny wolny indeks (po nieudanym zapisie indeksy mogą mieć luki).
    let n = Math.max(-1, ...[...wrap.querySelectorAll('[data-pub-row] input[name$="[id]"]')].map(i => parseInt(i.name.match(/pubs\[(\d+)\]/)?.[1] ?? -1, 10))) + 1;
    // Konta tylko wybranego klienta.
    const client = document.querySelector('[data-client-select]');
    const filter = () => document.querySelectorAll('[data-account-select] option[data-client], [data-campaign-select] option[data-client], [data-mat-picker] [data-client], [data-tag-sets] [data-client]').forEach(o => o.hidden = client.value && o.dataset.client !== client.value && !o.querySelector('input:checked'));
    client.addEventListener('change', filter); filter();
    // Zestaw hashtagów: dopisanie do pola (bez powtórzeń).
    document.querySelectorAll('[data-tag-sets] [data-tags]').forEach(b => b.addEventListener('click', () => {
        const input = document.querySelector('[data-hashtags]');
        const have = new Set(input.value.split(/\s+/).filter(Boolean));
        b.dataset.tags.split(/\s+/).filter(Boolean).forEach(t => have.add(t));
        input.value = [...have].join(' ');
    }));
    // „Publikuj automatycznie” tylko przy kontach połączonych z API.
    const autoToggle = row => {
        const sel = row.querySelector('[data-account-select]'), box = row.querySelector('[data-pub-auto]');
        if (!sel || !box) return;
        box.hidden = sel.selectedOptions[0]?.dataset.api !== '1';
    };
    wrap.addEventListener('change', e => { if (e.target.matches('[data-account-select]')) autoToggle(e.target.closest('[data-pub-row]')); });
    wrap.querySelectorAll('[data-pub-row]').forEach(autoToggle);
    document.querySelector('[data-add-pub]').addEventListener('click', () => {
        wrap.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', n++)); filter(); autoToggle(wrap.lastElementChild);
    });
})();
</script>
@endpush
