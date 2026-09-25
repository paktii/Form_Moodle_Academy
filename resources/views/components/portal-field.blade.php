@props(['name', 'label', 'value' => '', 'type' => 'text', 'placeholder' => '', 'required' => false, 'id' => null])
@php
    $inputId = $id ?? 'field-'.str_replace(['[', ']'], ['-', ''], $name);
    $errorName = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
@endphp
<label class="form-label" for="{{ $inputId }}">{{ $label }}@if($required)<span class="required-indicator" data-required-indicator aria-hidden="true">*</span>@else<span class="required-indicator" data-required-indicator aria-hidden="true" hidden>*</span>@endif
    <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($errorName, $value) }}" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->class(['form-field', 'form-field--error' => $errors->has($errorName)]) }}>
    @error($errorName)
        <span class="field-error" style="position: absolute; bottom: 0; left: 0; color: var(--portal-red); font-size: 13px; line-height: 1;">{{ $message }}</span>
    @enderror
</label>
