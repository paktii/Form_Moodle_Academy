@props(['name', 'label', 'value' => '', 'type' => 'text', 'placeholder' => '', 'required' => false, 'id' => null])
@php($inputId = $id ?? 'field-'.str_replace(['[', ']'], ['-', ''], $name))
<label class="form-label" for="{{ $inputId }}">{{ $label }}
    <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old(str_replace(['][', '[', ']'], ['.', '.', ''], $name), $value) }}" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->class(['form-field']) }}>
</label>
