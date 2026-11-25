{{-- Mini-wykres pozycji (wyżej = lepiej). --}}
@php
    $pts = $positions->filter(fn ($p) => $p->position)->values();
    $max = max(10, (int) $pts->max('position'));
    $n = $pts->count();
    $path = $n > 1 ? $pts->map(fn ($p, $i) => ($i ? 'L' : 'M').round($i * 96 / ($n - 1), 1).' '.round(2 + ($p->position - 1) / max(1, $max - 1) * 22, 1))->implode(' ') : '';
@endphp
<svg class="spark" viewBox="0 0 96 26" preserveAspectRatio="none" aria-hidden="true">@if($path)<path d="{{ $path }}" />@endif</svg>
