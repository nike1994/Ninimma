{{-- Gdzie materiał został użyty (posty) + „Utwórz post”. $m = Material --}}
@php($used = $m->usedPublications())
<div class="mat-used">
    @if($m->file && $m->usableForPost())
        <a href="{{ route('social.contents.create', ['material' => $m->id]) }}" class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /> {{ __('lic.create_post') }}</a>
    @elseif($m->file)
        <span class="btn btn-small is-disabled" title="{{ __('lic.cannot_post', ['material' => $m->description]) }}"><x-icon name="lock" :size="14" /> {{ __('lic.create_post') }}</span>
    @endif
    <x-to-board :usage="$m->file" />
    @if($used->isNotEmpty())
        <small class="muted">{{ __('lic.used_in') }}:</small>
        @foreach($used->take(6) as $p)
            <a href="{{ route('social.contents.edit', $p->content_id) }}" @class(['mat-used-pub', $p->status]) title="{{ $p->content->title }}">
                <x-platform :platform="$p->platform" :size="14" /> {{ $p->scheduled_at->format('d.m') }}</a>
        @endforeach
        @if($used->count() > 6)<small class="muted">+{{ $used->count() - 6 }}</small>@endif
    @else
        <small class="muted">{{ __('lic.not_used') }}</small>
    @endif
</div>
