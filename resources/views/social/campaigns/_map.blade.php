{{-- Mapa Polski z miastami odbiorców (uproszczony kontur). $cities = [nazwa => %] --}}
@php
    $toXY = fn ($lon, $lat) => [round(($lon - 14.0) / 10.3 * 300, 1), round((55.0 - $lat) / 6.1 * 290, 1)];
    $border = [[14.2,53.9],[14.6,54.0],[15.5,54.2],[16.5,54.55],[17.5,54.8],[18.3,54.83],[18.6,54.4],[19.0,54.35],[19.6,54.45],[20.5,54.4],[21.5,54.33],[22.8,54.36],[23.5,54.2],[23.5,53.9],[23.9,53.2],[23.6,52.6],[23.2,52.3],[23.6,51.7],[24.1,51.0],[23.9,50.5],[23.4,50.3],[22.7,49.6],[22.9,49.1],[22.5,49.05],[21.8,49.4],[20.9,49.3],[20.0,49.2],[19.4,49.6],[18.8,49.5],[18.0,50.0],[17.6,50.2],[16.9,50.4],[16.6,50.6],[16.2,50.65],[15.4,50.8],[14.8,50.9],[15.0,51.3],[14.7,52.0],[14.6,52.6],[14.1,52.85],[14.4,53.3]];
    $path = 'M '.collect($border)->map(fn ($p) => implode(' ', $toXY(...$p)))->implode(' L ').' Z';
    $geo = ['warszawa' => [21.01, 52.23], 'kraków' => [19.94, 50.06], 'krakow' => [19.94, 50.06], 'wrocław' => [17.03, 51.11], 'wroclaw' => [17.03, 51.11], 'poznań' => [16.93, 52.41], 'poznan' => [16.93, 52.41],
        'gdańsk' => [18.65, 54.35], 'gdansk' => [18.65, 54.35], 'łódź' => [19.46, 51.76], 'lodz' => [19.46, 51.76], 'szczecin' => [14.55, 53.43], 'bydgoszcz' => [18.0, 53.12], 'lublin' => [22.57, 51.25],
        'białystok' => [23.16, 53.13], 'bialystok' => [23.16, 53.13], 'katowice' => [19.02, 50.26], 'gdynia' => [18.53, 54.52], 'częstochowa' => [19.12, 50.81], 'radom' => [21.15, 51.4], 'toruń' => [18.6, 53.01],
        'torun' => [18.6, 53.01], 'rzeszów' => [22.0, 50.04], 'rzeszow' => [22.0, 50.04], 'kielce' => [20.63, 50.87], 'olsztyn' => [20.48, 53.78], 'opole' => [17.93, 50.67], 'zielona góra' => [15.5, 51.94],
        'gorzów wielkopolski' => [15.24, 52.73], 'sopot' => [18.56, 54.44], 'gliwice' => [18.67, 50.29], 'sosnowiec' => [19.13, 50.29], 'koszalin' => [16.18, 54.19], 'słupsk' => [17.03, 54.46], 'tarnów' => [20.99, 50.01], 'płock' => [19.7, 52.55]];
    $max = max([1, ...array_values($cities)]);
    $first = array_key_first($cities);
@endphp
<svg viewBox="-10 -10 320 310" class="mk-map" role="img" aria-label="{{ __('mkt.map') }}">
    <path d="{{ $path }}" class="mk-map-land"/>
    @foreach($cities as $name => $share)
        @php($g = $geo[mb_strtolower(trim(preg_replace('/\s*\(.*\)$/', '', $name)))] ?? null)
        @if($g)
            @php([$cx, $cy] = $toXY(...$g))
            @php($r = 4 + 9 * $share / $max)
            @if($name === $first)<circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r + 6 }}" class="mk-map-halo"/>@endif
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" @class(['mk-map-dot', 'top' => $name === $first])><title>{{ $name }}: {{ str_replace('.', ',', $share) }}%</title></circle>
        @endif
    @endforeach
</svg>
