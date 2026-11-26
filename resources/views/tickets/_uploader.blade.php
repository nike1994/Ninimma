{{-- Załączniki: przeciągnij, wklej ze schowka, wybierz plik lub nagraj ekran. --}}
<div class="uploader" data-uploader
     data-start="{{ route('uploads.start') }}"
     data-poster="{{ route('files.poster.store', '__ID__') }}"
     data-max-mb="{{ auth()->user()->isAdmin() ? 0 : config('agency.client_max_file_mb') }}"
     data-max-rec="{{ auth()->user()->isAdmin() ? 0 : config('agency.client_max_recording_seconds') }}">
    <div class="uploader-list"></div>
    <div class="uploader-actions">
        <label class="btn btn-pill"><x-icon name="paperclip" :size="17" />{{ __('ui.attach') }}<input type="file" multiple hidden></label>
        <button type="button" class="btn btn-pill" data-record><x-icon name="video" :size="17" /><span>{{ __('ui.record_screen') }}</span></button>
        <span class="muted small hide-sm">{{ __('ui.paste_hint') }}</span>
        @unless(isset($noSend))<button class="btn btn-send"><x-icon name="send" :size="18" />{{ __('ui.send') }}</button>@endunless
    </div>
</div>
