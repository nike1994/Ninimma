@props(['icon', 'value', 'label', 'tone' => 'green', 'href' => null])
@if($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => 'stat stat-'.$tone]) }}>@else<div {{ $attributes->merge(['class' => 'stat stat-'.$tone]) }}>@endif
    <span class="stat-icon"><x-icon :name="$icon" :size="26" /></span>
    <span class="stat-text"><strong>{{ $value }}</strong><span>{{ $label }}</span></span>
@if($href)</a>@else</div>@endif
