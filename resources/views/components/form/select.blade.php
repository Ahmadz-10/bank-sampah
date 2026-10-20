@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">
        {{ __($label) }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    <select id="{{ $name }}" name="{{ $name }}"
            {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}
            @required($required)>
        @if ($placeholder)
            <option value="">{{ __($placeholder) }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <div class="form-text">{{ __($help) }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
