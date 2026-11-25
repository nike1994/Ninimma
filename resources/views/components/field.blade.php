@props(['name', 'label', 'type' => 'text', 'value' => null])
<label class="field">
    <span>{{ $label }}</span>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes }}>
    @endif
    @error($name)<small class="error">{{ $message }}</small>@enderror
</label>
