@props(['name', 'size' => 40])
@php
    $words = preg_split('/\s+/u', trim((string) $name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
    $variant = crc32((string) $name) % 4;
@endphp
<span {{ $attributes->merge(['class' => 'avatar avatar-v'.$variant]) }} style="--s: {{ $size }}px">{{ $initials }}</span>
