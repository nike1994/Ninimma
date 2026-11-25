{{-- Wykres kołowy udziału platform w zasięgu. --}}
@php
    $r = 52; $c = 2 * M_PI * $r; $offset = 0;
@endphp
<div class="donut-wrap">
    <svg class="donut" viewBox="0 0 140 140" role="img" aria-label="{{ __('social.by_platform') }}">
        <circle cx="70" cy="70" r="{{ $r }}" fill="none" stroke="#eef3f8" stroke-width="20"/>
        @foreach($platforms as $p)
            @php
                $len = $totalReach ? $c * $p->reach / $totalReach : 0;
            @endphp
            <circle cx="70" cy="70" r="{{ $r }}" fill="none" stroke="{{ $p->platform->color() }}" stroke-width="20"
                    stroke-dasharray="{{ max(0, $len - 1.5) }} {{ $c }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 70 70)"/>
            @php
                $offset += $len;
            @endphp
        @endforeach
    </svg>
    <div class="donut-center"><strong>{{ \App\Support\Num::short($totalReach) }}</strong><small>{{ __('social.reach_lc') }}</small></div>
</div>
