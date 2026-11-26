@extends('layouts.app')
@section('title', $content->exists ? $content->title : __('social.new_content'))
@section('content')
<div class="page-head">
    <h1 class="page-title">{{ $content->exists ? $content->title : __('social.new_content') }}</h1>
    @if($content->exists)
        <form method="post" action="{{ route('social.contents.destroy', $content) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('delete')<button class="btn btn-small">{{ __('social.delete') }}</button></form>
    @endif
</div>
<form method="post" action="{{ $content->exists ? route('social.contents.update', $content) : route('social.contents.store') }}" class="content-form" data-message-form>
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
        <x-field name="description" type="textarea" :label="__('ui.description')" :value="$content->description" rows="5" data-paste-target />
        <x-field name="hashtags" :label="__('social.hashtags')" :value="$content->hashtags" placeholder="#marka #produkt" />
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
        @if($errors->has('pubs.*'))
            <div class="lic-issues">@foreach(collect($errors->get('pubs.*'))->flatten()->unique() as $msg)<div class="lic-issue lic-issue-error"><x-icon name="lock" :size="16" /><span>{{ $msg }}</span></div>@endforeach</div>
        @endif
        <div data-pubs>
            @php($pubs = $content->exists ? $content->publications()->with('measurements')->orderBy('scheduled_at')->get() : collect())
            @foreach($pubs as $i => $p) @include('social.contents._pub', ['i' => $i, 'p' => $p]) @endforeach
            @include('social.contents._pub', ['i' => $pubs->count(), 'p' => null])
        </div>
        <template data-pub-template>@include('social.contents._pub', ['i' => '__I__', 'p' => null])</template>
        <button type="button" class="btn btn-small" data-add-pub><x-icon name="plus" :size="14" /> {{ __('social.add_publication') }}</button>
    </section>
    <button class="btn btn-send btn-block"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>
</form>
@endsection
@push('scripts')
<script src="{{ asset('js/uploader.js') }}?v=2"></script>
<script>
(function () {
    const wrap = document.querySelector('[data-pubs]'), tpl = document.querySelector('[data-pub-template]');
    let n = wrap.querySelectorAll('[data-pub-row]').length;
    // Konta tylko wybranego klienta.
    const client = document.querySelector('[data-client-select]');
    const filter = () => document.querySelectorAll('[data-account-select] option[data-client], [data-campaign-select] option[data-client], [data-mat-picker] [data-client]').forEach(o => o.hidden = client.value && o.dataset.client !== client.value && !o.querySelector('input:checked'));
    client.addEventListener('change', filter); filter();
    document.querySelector('[data-add-pub]').addEventListener('click', () => {
        wrap.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', n++)); filter();
    });
})();
</script>
@endpush
