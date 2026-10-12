@props(['name', 'label', 'value' => null, 'required' => false, 'rows' => 3, 'help' => null])

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">
        {{ __($label) }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
              @required($required)>{{ old($name, $value) }}</textarea>
    @if ($help)
        <div class="form-text">{{ __($help) }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
