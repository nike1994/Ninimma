{{-- Zdjęcie profilu konta (albo inicjały) z plakietką platformy. --}}
@props(['account', 'size' => 42, 'badge' => true])
<span {{ $attributes->merge(['class' => 'acc-avatar']) }} style="--s: {{ $size }}px">
    @if($account->avatarUrl())<img src="{{ $account->avatarUrl() }}" alt="" loading="lazy">@else<x-avatar :name="$account->name" :size="$size" />@endif
    @if($badge)<x-platform :platform="$account->platform" :size="(int) round($size * .43)" class="acc-badge" />@endif
</span>
