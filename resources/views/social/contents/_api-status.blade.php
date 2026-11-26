{{-- Stan automatycznej publikacji. $p = Publication --}}
@if($p->api_status)
    <span @class(['api-status', 'api-'.$p->api_status]) @if($p->api_error) title="{{ $p->api_error }}" @endif>
        <x-icon :name="match ($p->api_status) { 'published' => 'check-circle', 'failed' => 'alert', 'held' => 'pause', default => 'loader' }" :size="14" />
        {{ __('api.status_'.$p->api_status) }}@if($p->api_status === 'published' && $p->url) · <a href="{{ $p->url }}" target="_blank" rel="noopener">{{ __('social.link') }}</a>@endif
    </span>
    @if($p->api_error && in_array($p->api_status, ['failed', 'held'], true))<small class="api-error">{{ $p->api_error }}</small>@endif
@endif
