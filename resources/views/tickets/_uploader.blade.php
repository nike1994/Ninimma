{{-- Załączniki: przeciągnij, wklej ze schowka, wybierz plik lub nagraj ekran. --}}
<div class="uploader" data-uploader
     data-start="{{ route('uploads.start') }}"
     data-max-mb="{{ auth()->user()->isAdmin() ? 0 : config('agency.client_max_file_mb') }}"
     data-max-rec="{{ auth()->user()->isAdmin() ? 0 : config('agency.client_max_recording_seconds') }}">
    <div class="uploader-list"></div>
    <div class="uploader-actions">
        <label class="btn btn-small">📎 {{ __('ui.attach') }}<input type="file" multiple hidden></label>
        <button type="button" class="btn btn-small" data-record>⏺ {{ __('ui.record_screen') }}</button>
        <span class="muted small">{{ __('ui.paste_hint') }}</span>
    </div>
</div>
