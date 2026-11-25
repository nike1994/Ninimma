@props(['variant' => 'sidebar'])
@php($id = 'w'.substr(md5($variant.uniqid()), 0, 6))
@if($variant === 'sidebar')
{{-- Miękkie, półprzezroczyste fale z gradientem (jasny grzbiet → ciemny dół), nachodzące na siebie. --}}
<svg class="waves waves-sidebar" viewBox="0 0 300 240" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $id }}a" x1=".2" y1="0" x2=".5" y2="1"><stop offset="0" stop-color="#7ee0a8"/><stop offset=".45" stop-color="#19b3c0"/><stop offset="1" stop-color="#0b5f9c"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1=".3" x2="1" y2=".2"><stop offset="0" stop-color="#0e64a8"/><stop offset=".55" stop-color="#1a9fd8"/><stop offset="1" stop-color="#3fd0c0"/></linearGradient>
        <linearGradient id="{{ $id }}c" x1="1" y1="0" x2=".3" y2="1"><stop offset="0" stop-color="#c3f06e"/><stop offset=".45" stop-color="#4cc95a"/><stop offset="1" stop-color="#139c8e"/></linearGradient>
        <linearGradient id="{{ $id }}d" x1="0" y1="0" x2=".8" y2="1"><stop offset="0" stop-color="#1a86c8"/><stop offset="1" stop-color="#0a3966"/></linearGradient>
        <linearGradient id="{{ $id }}h" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
    </defs>
    {{-- tylna, wysoka plama po lewej --}}
    <path d="M0 52 C 55 18, 140 28, 136 108 C 133 165, 70 205, 0 236 Z" fill="url(#{{ $id }}a)"/>
    {{-- środkowe wzgórze po prawej --}}
    <path d="M40 240 C 80 175, 130 128, 196 138 C 246 146, 262 182, 300 158 V240 Z" fill="url(#{{ $id }}b)" opacity=".92"/>
    {{-- przednia zielona fala od prawej --}}
    <path d="M110 240 C 160 206, 225 168, 300 150 V240 Z" fill="url(#{{ $id }}c)" opacity=".95"/>
    {{-- przednia granatowa fala od lewej --}}
    <path d="M0 168 C 55 150, 105 172, 140 212 C 152 226, 160 236, 166 240 H0 Z" fill="url(#{{ $id }}d)"/>
</svg>
@elseif($variant === 'corner')
<svg class="waves waves-corner" viewBox="0 0 400 300" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $id }}a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9be15d"/><stop offset=".5" stop-color="#14a7b8"/><stop offset="1" stop-color="#0b6aa8"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#13a3b4" stop-opacity=".9"/><stop offset="1" stop-color="#0b2a4a"/></linearGradient>
    </defs>
    <path d="M140 0 C 200 60, 300 40, 330 110 S 360 260, 400 300 L400 0 Z" fill="url(#{{ $id }}a)" opacity=".9"/>
    <path d="M260 0 C 300 40, 380 50, 400 120 L400 0 Z" fill="url(#{{ $id }}b)" opacity=".85"/>
</svg>
@else
<svg class="waves waves-card" viewBox="0 0 200 120" preserveAspectRatio="none" aria-hidden="true">
    <defs><linearGradient id="{{ $id }}a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9be15d"/><stop offset=".6" stop-color="#14a7b8"/><stop offset="1" stop-color="#0b6aa8" stop-opacity="0"/></linearGradient></defs>
    <path d="M90 0 C 120 40, 170 30, 200 70 L200 0 Z" fill="url(#{{ $id }}a)" opacity=".85"/>
    <path d="M140 0 C 160 20, 190 15, 200 35 L200 0 Z" fill="#fff" opacity=".15"/>
</svg>
@endif
