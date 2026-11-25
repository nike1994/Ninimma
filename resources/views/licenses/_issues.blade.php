{{-- Lista konfliktów licencji. $issues --}}
@if($issues->isNotEmpty())
    <div class="lic-issues">
        @foreach($issues as $i)
            <a href="{{ $i->url }}" class="lic-issue lic-issue-{{ $i->severity }}" @if(str_starts_with($i->url, 'http') && ! str_starts_with($i->url, url('/'))) target="_blank" rel="noopener" @endif>
                <x-icon :name="$i->severity === 'error' ? 'alert' : 'clock'" :size="16" />
                <span><strong>{{ __('lic.type_'.$i->type) }}</strong> {{ $i->message }}</span>
            </a>
        @endforeach
    </div>
@endif
