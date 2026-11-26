{{-- Jeden post w planowaniu seryjnym. $i, $r (wpisane dane), $files (FileUsage) --}}
<article @class(['batch-row', 'has-error' => is_int($i) && $errors->has('rows.'.$i.'.*')]) data-row>
    <span class="batch-num" data-num></span>
    <div class="batch-media" data-media>
        @foreach($files as $f)
            <span class="batch-thumb">@if($f->file->isImage())<img src="{{ route('files.show', [$f, $f->name]) }}" alt="">@else<x-icon name="video" :size="22" />@endif
                <input type="hidden" name="rows[{{ $i }}][uploads][]" value="{{ $f->id }}"></span>
        @endforeach
        <label class="batch-thumb batch-add-file" title="{{ __('social.batch_add_file') }}"><input type="file" multiple accept="image/*,video/*" hidden data-row-files><x-icon name="plus" :size="18" /></label>
    </div>
    <div class="batch-fields">
        <div class="batch-when">
            <input type="date" name="rows[{{ $i }}][date]" value="{{ $r['date'] ?? '' }}" aria-label="{{ __('social.batch_date') }}" data-date>
            <input type="time" name="rows[{{ $i }}][time]" value="{{ $r['time'] ?? '' }}" aria-label="{{ __('social.batch_time') }}" title="{{ __('social.batch_time_hint') }}">
            <input name="rows[{{ $i }}][title]" value="{{ $r['title'] ?? '' }}" placeholder="{{ __('social.batch_title_ph') }}" maxlength="200" class="batch-title">
        </div>
        <textarea name="rows[{{ $i }}][description]" rows="2" placeholder="{{ __('social.batch_desc_ph') }}">{{ $r['description'] ?? '' }}</textarea>
        <details @if(! empty($r['hashtags'])) open @endif><summary class="small muted">{{ __('social.batch_own_tags') }}</summary>
            <input name="rows[{{ $i }}][hashtags]" value="{{ $r['hashtags'] ?? '' }}" placeholder="#inne #hashtagi"></details>
        @if(is_int($i))@foreach(collect($errors->get('rows.'.$i.'.*'))->flatten()->unique() as $msg)<small class="error">{{ $msg }}</small>@endforeach @endif
    </div>
    <button type="button" class="icon-btn icon-btn-sm batch-remove" data-remove aria-label="{{ __('social.remove') }}"><x-icon name="x" :size="16" /></button>
</article>
