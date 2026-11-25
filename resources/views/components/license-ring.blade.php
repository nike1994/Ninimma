{{-- Licznik licencji w kształcie pierścienia: ile zostało z okresu licencji. --}}
@props(['material', 'size' => 64])
@php
    $st = \App\Services\LicenseGuard::status($material);
    $r = 26; $len = 2 * M_PI * $r;
    $pct = $st->state === 'none' ? 100 : ($st->state === 'expired' ? 100 : $st->pct_left);
    $big = match ($st->state) {
        'none' => '∞',
        'upcoming' => $st->starts_in,
        'expired' => abs($st->days_left),
        default => $st->days_left,
    };
    $small = match ($st->state) {
        'none' => __('lic.ring_none'),
        'upcoming' => __('lic.ring_starts'),
        'expired' => __('lic.ring_ago'),
        default => trans_choice('lic.ring_days', $st->days_left),
    };
    $gid = 'lr'.$material->id.'-'.$size;
@endphp
<span {{ $attributes->merge(['class' => 'lic-ring lic-'.$st->state]) }} style="--s: {{ $size }}px" title="{{ __('lic.state_'.$st->state) }}{{ $material->license_until ? ' · '.__('lic.until').' '.$material->license_until->format('d.m.Y') : '' }}">
    <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" aria-hidden="true">
        <defs><linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
            @switch($st->state)
                @case('expired')<stop offset="0" stop-color="#ff7aa2"/><stop offset="1" stop-color="#d23f3f"/>@break
                @case('expiring')<stop offset="0" stop-color="#ffc24b"/><stop offset="1" stop-color="#e0457b"/>@break
                @case('upcoming')<stop offset="0" stop-color="#7fb8ff"/><stop offset="1" stop-color="#0b6aa8"/>@break
                @case('none')<stop offset="0" stop-color="#cfd9e4"/><stop offset="1" stop-color="#a9b7c6"/>@break
                @default<stop offset="0" stop-color="#9be15d"/><stop offset=".5" stop-color="#13a3b4"/><stop offset="1" stop-color="#0b6aa8"/>
            @endswitch
        </linearGradient></defs>
        <circle cx="32" cy="32" r="{{ $r }}" fill="none" stroke="#edf2f7" stroke-width="7"/>
        <circle cx="32" cy="32" r="{{ $r }}" fill="none" stroke="url(#{{ $gid }})" stroke-width="7" stroke-linecap="round"
            stroke-dasharray="{{ round($len * $pct / 100, 2) }} {{ round($len, 2) }}" transform="rotate(-90 32 32)"/>
    </svg>
    <span class="lic-ring-text"><strong>{{ $big }}</strong><small>{{ $small }}</small></span>
</span>
