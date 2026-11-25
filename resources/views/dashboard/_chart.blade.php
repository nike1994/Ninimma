{{-- Wykres aktywności zespołu: dwie gładkie linie z wypełnieniem gradientem, podpowiedź po najechaniu. --}}
@php
    $W = 640; $H = 230; $L = 34; $B = 26; $T = 12; $R = 10;
    $n = max(1, count($activity));
    $max = \App\Support\Chart::niceMax(max(1, collect($activity)->max('messages'), collect($activity)->max('actions')));
    $x = fn ($i) => $n === 1 ? ($L + $W - $R) / 2 : $L + $i * ($W - $L - $R) / ($n - 1);
    $y = fn ($v) => $T + ($H - $T - $B) * (1 - $v / $max);
    $pm = []; $pa = [];
    foreach ($activity as $i => $d) { $pm[] = [$x($i), $y($d['messages'])]; $pa[] = [$x($i), $y($d['actions'])]; }
    $lineM = \App\Support\Chart::smoothPath($pm);
    $lineA = \App\Support\Chart::smoothPath($pa);
    $base = $H - $B;
    $areaM = $lineM ? $lineM.sprintf(' L%.1f %.1f L%.1f %.1f Z', end($pm)[0], $base, $pm[0][0], $base) : '';
    $areaA = $lineA ? $lineA.sprintf(' L%.1f %.1f L%.1f %.1f Z', end($pa)[0], $base, $pa[0][0], $base) : '';
    $every = $n > 14 ? (int) ceil($n / 8) : 1;
@endphp
<div class="chart" data-chart>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" role="img" aria-label="{{ __('ui.team_activity') }}">
        <defs>
            <linearGradient id="chartM" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a9fd8" stop-opacity=".28"/><stop offset="1" stop-color="#1a9fd8" stop-opacity="0"/></linearGradient>
            <linearGradient id="chartA" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#36b45c" stop-opacity=".25"/><stop offset="1" stop-color="#36b45c" stop-opacity="0"/></linearGradient>
        </defs>
        @for($k = 0; $k <= 4; $k++)
            @php($gy = $T + ($H - $T - $B) * $k / 4)
            <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $gy }}" y2="{{ $gy }}" class="grid-line"/>
            <text x="{{ $L - 8 }}" y="{{ $gy + 4 }}" class="axis" text-anchor="end">{{ (int) round($max * (4 - $k) / 4) }}</text>
        @endfor
        <path d="{{ $areaM }}" fill="url(#chartM)"/>
        <path d="{{ $areaA }}" fill="url(#chartA)"/>
        <path d="{{ $lineM }}" class="line line-m"/>
        <path d="{{ $lineA }}" class="line line-a"/>
        @foreach($activity as $i => $d)
            @if($i % $every === 0 || $i === $n - 1)
                <text x="{{ $x($i) }}" y="{{ $H - 6 }}" class="axis" text-anchor="middle">{{ $d['label'] }}</text>
            @endif
            <g class="hover" data-title="{{ $d['title'] }}" data-m="{{ $d['messages'] }}" data-a="{{ $d['actions'] }}">
                <rect x="{{ $x($i) - ($W - $L - $R) / max(1, $n - 1) / 2 }}" y="0" width="{{ ($W - $L - $R) / max(1, $n - 1) }}" height="{{ $H }}" fill="transparent"/>
                <line x1="{{ $x($i) }}" x2="{{ $x($i) }}" y1="{{ $T }}" y2="{{ $base }}" class="guide"/>
                <circle cx="{{ $x($i) }}" cy="{{ $y($d['messages']) }}" r="4.5" class="dot-m"/>
                <circle cx="{{ $x($i) }}" cy="{{ $y($d['actions']) }}" r="4.5" class="dot-a"/>
            </g>
        @endforeach
    </svg>
    <div class="chart-tip" hidden><strong></strong><span><i class="m"></i>{{ __('ui.messages') }}: <b data-m></b></span><span><i class="a"></i>{{ __('ui.team_actions') }}: <b data-a></b></span></div>
</div>
