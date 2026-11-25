@props(['variant' => 'sidebar'])
@php($id = 'w'.substr(md5($variant.uniqid()), 0, 6))
@if($variant === 'sidebar')
{{-- Faliste pasy na całą szerokość, każdy z własnym gradientem, nachodzące na siebie (bez kół i cieni). --}}
<svg class="waves waves-sidebar" viewBox="0 0 300 220" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $id }}a" x1="0" y1="0" x2="1" y2=".6"><stop offset="0" stop-color="#1a9fd8"/><stop offset=".5" stop-color="#19b3c0"/><stop offset="1" stop-color="#7ee0c0"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="1" y1="0" x2="0" y2=".7"><stop offset="0" stop-color="#c3f06e"/><stop offset=".45" stop-color="#4cc95a"/><stop offset="1" stop-color="#14a7a0"/></linearGradient>
        <linearGradient id="{{ $id }}c" x1="0" y1="0" x2="1" y2=".4"><stop offset="0" stop-color="#14a7b8"/><stop offset=".55" stop-color="#1689c4"/><stop offset="1" stop-color="#0b5f9c"/></linearGradient>
        <linearGradient id="{{ $id }}d" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0b4f86"/><stop offset="1" stop-color="#0b2a4a"/></linearGradient>
        <linearGradient id="{{ $id }}f" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#06182c" stop-opacity=".35"/></linearGradient>
    </defs>
    <path d="M0 88 C 45 58, 95 58, 150 84 S 250 116, 300 70 V220 H0 Z" fill="url(#{{ $id }}a)" opacity=".75"/>
    <path d="M0 112 C 60 86, 120 136, 182 118 S 268 82, 300 100 V220 H0 Z" fill="url(#{{ $id }}b)" opacity=".9"/>
    <path d="M0 148 C 55 124, 112 164, 170 150 S 258 116, 300 136 V220 H0 Z" fill="url(#{{ $id }}c)" opacity=".92"/>
    <path d="M0 184 C 70 162, 140 196, 212 180 S 280 160, 300 170 V220 H0 Z" fill="url(#{{ $id }}d)"/>
    <rect x="0" y="60" width="300" height="160" fill="url(#{{ $id }}f)"/>
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
