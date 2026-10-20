@props(['name', 'label', 'checked' => false, 'help' => null])

<div class="mb-3">
    {{-- Hidden 0 makes an unticked box submit "0" instead of nothing. --}}
    <input type="hidden" name="{{ $name }}" value="0">
    <div class="form-check">
        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1"
               {{ $attributes->class(['form-check-input']) }}
               @checked((bool) old($name, $checked))>
        <label class="form-check-label" for="{{ $name }}">{{ __($label) }}</label>
    </div>
    @if ($help)
        <div class="form-text">{{ __($help) }}</div>
    @endif
</div>
