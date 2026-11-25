@props(['variant' => 'sidebar'])
@php($id = 'w'.substr(md5($variant.uniqid()), 0, 6))
@if($variant === 'sidebar')
{{-- Nakładające się fale: grzbiety w różnych miejscach + cień na krawędzi każdej warstwy. --}}
<svg class="waves waves-sidebar" viewBox="0 0 300 220" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <filter id="{{ $id }}s" x="-10%" y="-30%" width="120%" height="160%">
            <feDropShadow dx="0" dy="-4" stdDeviation="4" flood-color="#06182c" flood-opacity=".45"/>
        </filter>
        <linearGradient id="{{ $id }}a" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#0b6aa8"/><stop offset="1" stop-color="#5fd3df"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#b4ec6a"/><stop offset=".55" stop-color="#36b45c"/><stop offset="1" stop-color="#13a3b4"/></linearGradient>
        <linearGradient id="{{ $id }}c" x1="1" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2cc58f"/><stop offset="1" stop-color="#0e8fa6"/></linearGradient>
        <linearGradient id="{{ $id }}d" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0b6aa8"/><stop offset="1" stop-color="#1689c4"/></linearGradient>
        <linearGradient id="{{ $id }}e" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0c3a66"/><stop offset="1" stop-color="#0b2a4a"/></linearGradient>
    </defs>
    <path d="M0 125 C 55 125, 95 35, 175 45 S 265 105, 300 55 V220 H0 Z" fill="url(#{{ $id }}a)" opacity=".75"/>
    <path d="M0 62 C 45 52, 95 112, 150 120 S 245 88, 300 128 V220 H0 Z" fill="url(#{{ $id }}b)" filter="url(#{{ $id }}s)"/>
    <path d="M0 160 C 65 112, 125 100, 185 138 S 270 172, 300 145 V220 H0 Z" fill="url(#{{ $id }}c)" filter="url(#{{ $id }}s)"/>
    <path d="M0 178 C 45 150, 105 196, 165 184 S 255 142, 300 172 V220 H0 Z" fill="url(#{{ $id }}d)" filter="url(#{{ $id }}s)"/>
    <path d="M0 206 C 80 188, 160 218, 300 196 V220 H0 Z" fill="url(#{{ $id }}e)" filter="url(#{{ $id }}s)"/>
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
