{{-- Trend zaangażowania: linie dla kont, pierwsza z wypełnieniem gradientem. --}}
@php
    $W = 640; $H = 220; $L = 46; $B = 26; $T = 10; $R = 10;
    $n = max(1, count($trend['labels']));
    $all = collect($trend['lines'])->flatMap(fn ($l) => $l['values']);
    $max = \App\Support\Chart::niceMax(max(1, (int) $all->max()));
    $x = fn ($i) => $n === 1 ? ($L + $W - $R) / 2 : $L + $i * ($W - $L - $R) / ($n - 1);
    $y = fn ($v) => $T + ($H - $T - $B) * (1 - $v / $max);
    $base = $H - $B;
    $every = $n > 10 ? (int) ceil($n / 7) : 1;
    $fmt = fn ($v) => $v >= 1000 ? str_replace('.', ',', round($v / 1000, 1)).' '.__('social.thousand') : $v;
@endphp
<div class="chart" data-chart>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" role="img" aria-label="{{ __('social.engagement_trend') }}">
        <defs>
            @foreach($trend['lines'] as $i => $line)
                <linearGradient id="tr{{ $i }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{{ $line['color'] }}" stop-opacity=".22"/><stop offset="1" stop-color="{{ $line['color'] }}" stop-opacity="0"/></linearGradient>
            @endforeach
        </defs>
        @for($k = 0; $k <= 4; $k++)
            @php
                $gy = $T + ($H - $T - $B) * $k / 4;
            @endphp
            <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $gy }}" y2="{{ $gy }}" class="grid-line"/>
            <text x="{{ $L - 8 }}" y="{{ $gy + 4 }}" class="axis" text-anchor="end">{{ $fmt((int) round($max * (4 - $k) / 4)) }}</text>
        @endfor
        @foreach($trend['lines'] as $i => $line)
            @php
                $pts = array_map(fn ($v, $j) => [$x($j), $y($v)], $line['values'], array_keys($line['values']));
                $path = \App\Support\Chart::smoothPath($pts);
            @endphp
            @if($path)
                <path d="{{ $path }} L{{ end($pts)[0] }} {{ $base }} L{{ $pts[0][0] }} {{ $base }} Z" fill="url(#tr{{ $i }})"/>
                <path d="{{ $path }}" class="line" stroke="{{ $line['color'] }}"/>
                <circle cx="{{ end($pts)[0] }}" cy="{{ end($pts)[1] }}" r="4" fill="{{ $line['color'] }}" stroke="#fff" stroke-width="2" vector-effect="non-scaling-stroke"/>
            @endif
        @endforeach
        @foreach($trend['labels'] as $i => $label)
            @if($i % $every === 0 || $i === $n - 1)<text x="{{ $x($i) }}" y="{{ $H - 6 }}" class="axis" text-anchor="middle">{{ $label }}</text>@endif
            <g class="hover" data-title="{{ $trend['titles'][$i] }}" data-values='@json(collect($trend['lines'])->map(fn ($l) => [$l['name'], $l['color'], $l['values'][$i]])->values())'>
                <rect x="{{ $x($i) - ($W - $L - $R) / max(1, $n - 1) / 2 }}" y="0" width="{{ ($W - $L - $R) / max(1, $n - 1) }}" height="{{ $H }}" fill="transparent"/>
                <line x1="{{ $x($i) }}" x2="{{ $x($i) }}" y1="{{ $T }}" y2="{{ $base }}" class="guide"/>
                @foreach($trend['lines'] as $line)<circle cx="{{ $x($i) }}" cy="{{ $y($line['values'][$i]) }}" r="4" fill="{{ $line['color'] }}"/>@endforeach
            </g>
        @endforeach
    </svg>
    <div class="chart-tip" hidden><strong></strong><div data-rows></div></div>
</div>
