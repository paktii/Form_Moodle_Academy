@props(['name', 'label', 'options' => [], 'value' => '', 'placeholder' => 'พิมพ์เพื่อค้นหา', 'required' => false, 'id' => null])
@php
    $inputId = $id ?? 'field-'.str_replace(['[', ']'], ['-', ''], $name);
    $listId = $inputId.'-options';
    $selectedValue = old($name, $value);
    $selectedLabel = $options[$selectedValue] ?? '';
    $searchValue = old($name.'_search', $selectedLabel);
@endphp
<label class="form-label" for="{{ $inputId }}" data-searchable-select>{{ $label }}@if($required)<span class="required-indicator" aria-hidden="true">*</span>@endif
    <div class="searchable-select">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}_search"
            type="search"
            class="form-field searchable-select__input"
            value="{{ $searchValue }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listId }}"
            aria-expanded="false"
            data-searchable-select-input
            @required($required)
        >
        <span class="searchable-select__arrow" aria-hidden="true" @if(filled($searchValue)) hidden @endif data-searchable-select-arrow></span>
        <button
            type="button"
            class="searchable-select__clear"
            aria-label="ล้างหน่วยงานเป้าหมาย"
            @if(blank($searchValue)) hidden @endif
            data-searchable-select-clear
        >×</button>
        <input type="hidden" name="{{ $name }}" value="{{ $selectedValue }}" data-searchable-select-value>
        <div id="{{ $listId }}" class="searchable-select__menu" role="listbox" hidden data-searchable-select-menu>
            @foreach($options as $optionValue => $optionLabel)
                <button
                    id="{{ $inputId }}-option-{{ $optionValue }}"
                    type="button"
                    class="searchable-select__option"
                    role="option"
                    aria-selected="{{ (string) $selectedValue === (string) $optionValue ? 'true' : 'false' }}"
                    data-searchable-select-option
                    data-value="{{ $optionValue }}"
                >{{ $optionLabel }}</button>
            @endforeach
            <p class="searchable-select__empty" role="status" hidden data-searchable-select-empty>ไม่พบหน่วยงานที่ค้นหา</p>
        </div>
    </div>
</label>
