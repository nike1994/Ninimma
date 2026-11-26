{{-- Wyniki w czasie: zasięg i kliknięcia (osobne skale — kliknięć jest o rząd mniej). $s = series --}}
@php
    $W = 560; $H = 190; $L = 46; $B = 24; $T = 10; $R = 12;
    $n = count($s['labels']);
    $maxR = \App\Support\Chart::niceMax(max(1, (int) max($s['reach'] ?: [0])));
    $maxC = max(1, (int) max($s['clicks'] ?: [0]));
    $x = fn ($i) => $n <= 1 ? ($L + $W - $R) / 2 : $L + $i * ($W - $L - $R) / ($n - 1);
    $yR = fn ($v) => $T + ($H - $T - $B) * (1 - $v / $maxR);
    $yC = fn ($v) => $T + ($H - $T - $B) * (1 - $v / ($maxC * 1.08));
    $ptsR = collect($s['reach'])->map(fn ($v, $i) => [$x($i), $yR($v)])->all();
    $ptsC = collect($s['clicks'])->map(fn ($v, $i) => [$x($i), $yC($v)])->all();
    $every = max(1, (int) ceil($n / 7));
    $fmt = fn ($v) => $v >= 1000 ? str_replace('.', ',', round($v / 1000, 0)).' '.__('social.thousand') : $v;
@endphp
<div class="chart mk-chart">
    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" role="img" aria-label="{{ __('mkt.over_time') }}">
        <defs><linearGradient id="mkR" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a9fd8" stop-opacity=".2"/><stop offset="1" stop-color="#1a9fd8" stop-opacity="0"/></linearGradient></defs>
        @for($k = 0; $k <= 4; $k++)
            @php($yy = $T + ($H - $T - $B) * $k / 4)
            <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $yy }}" y2="{{ $yy }}" class="grid"/>
            <text x="{{ $L - 8 }}" y="{{ $yy + 4 }}" text-anchor="end" class="axis">{{ $fmt((int) round($maxR * (4 - $k) / 4)) }}</text>
        @endfor
        @if($n > 1)
            <path d="{{ \App\Support\Chart::smoothPath($ptsR) }} L {{ $x($n - 1) }} {{ $H - $B }} L {{ $x(0) }} {{ $H - $B }} Z" fill="url(#mkR)"/>
            <path d="{{ \App\Support\Chart::smoothPath($ptsR) }}" fill="none" stroke="#1a9fd8" stroke-width="2.6" vector-effect="non-scaling-stroke"/>
            <path d="{{ \App\Support\Chart::smoothPath($ptsC) }}" fill="none" stroke="#36b45c" stroke-width="2.2" vector-effect="non-scaling-stroke"/>
            <circle cx="{{ $x($n - 1) }}" cy="{{ $yR(end($s['reach'])) }}" r="4" fill="#1a9fd8" stroke="#fff" stroke-width="2"/>
            <circle cx="{{ $x($n - 1) }}" cy="{{ $yC(end($s['clicks'])) }}" r="4" fill="#36b45c" stroke="#fff" stroke-width="2"/>
        @endif
        @foreach($s['labels'] as $i => $d)
            @if($i % $every === 0 || $i === $n - 1)<text x="{{ $x($i) }}" y="{{ $H - 6 }}" text-anchor="middle" class="axis">{{ $d->translatedFormat('j M') }}</text>@endif
        @endforeach
    </svg>
</div>
