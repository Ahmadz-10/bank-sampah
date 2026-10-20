@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">
        {{ __($label) }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
           @required($required)>
    @if ($help)
        <div class="form-text">{{ __($help) }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
