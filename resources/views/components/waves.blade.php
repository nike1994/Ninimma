@props(['variant' => 'sidebar'])
@php($id = 'w'.substr(md5($variant.uniqid()), 0, 6))
@if($variant === 'sidebar')
{{-- Nakładające się fale: każda warstwa ma własny gradient i przesuniętą fazę. --}}
<svg class="waves waves-sidebar" viewBox="0 0 300 200" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $id }}a" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0b6aa8"/><stop offset="1" stop-color="#13a3b4"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#13a3b4"/><stop offset=".6" stop-color="#2cc58f"/><stop offset="1" stop-color="#9be15d"/></linearGradient>
        <linearGradient id="{{ $id }}c" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#36b45c"/><stop offset="1" stop-color="#13a3b4"/></linearGradient>
        <linearGradient id="{{ $id }}d" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0b4f86"/><stop offset=".5" stop-color="#0b6aa8"/><stop offset="1" stop-color="#13a3b4"/></linearGradient>
        <linearGradient id="{{ $id }}e" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#9be15d"/><stop offset="1" stop-color="#36b45c" stop-opacity="0"/></linearGradient>
    </defs>
    <path d="M0 62 C 45 30, 95 30, 145 58 S 245 92, 300 48 L300 200 L0 200 Z" fill="url(#{{ $id }}a)" opacity=".55"/>
    <path d="M0 92 C 55 62, 110 70, 160 96 S 255 118, 300 82 L300 200 L0 200 Z" fill="url(#{{ $id }}b)" opacity=".9"/>
    <path d="M0 108 C 40 100, 70 104, 100 112" fill="none" stroke="url(#{{ $id }}e)" stroke-width="2" opacity=".7"/>
    <path d="M0 128 C 60 104, 120 118, 175 136 S 260 150, 300 118 L300 200 L0 200 Z" fill="url(#{{ $id }}c)" opacity=".85"/>
    <path d="M0 158 C 70 132, 140 150, 200 164 S 270 168, 300 150 L300 200 L0 200 Z" fill="url(#{{ $id }}d)"/>
    <path d="M0 182 C 80 166, 160 182, 230 186 S 285 180, 300 176 L300 200 L0 200 Z" fill="#0b2a4a" opacity=".55"/>
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
