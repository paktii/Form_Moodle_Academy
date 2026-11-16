@props(['name', 'label', 'placeholder' => 'พิมพ์ชื่อ–นามสกุล หรือ Buasri ID', 'id' => null, 'required' => true, 'hideLabel' => false, 'dependsOn' => null, 'dependencyPlaceholder' => 'กรุณาเลือกส่วนงานก่อน', 'searchUrl' => null, 'value' => null, 'searchValue' => null])
@php
    $inputId = $id ?? 'field-'.$name;
    $listId = $inputId.'-options';
    $selectedValue = old($name, $value ?? '');
    $searchValue = old($name.'_search', $searchValue ?? $selectedValue);
    $dependencyValue = filled($dependsOn) ? old($dependsOn, '') : null;
    $dependencyDisabled = filled($dependsOn) && blank($dependencyValue);
    $personSearchUrl = $searchUrl ?? route('admin.personnel.search');
@endphp
<label class="form-label" for="{{ $inputId }}" data-admin-person-search data-search-url="{{ $personSearchUrl }}" @if($dependsOn) data-admin-person-depends-on="{{ $dependsOn }}" @endif>
    <span @class(['sr-only' => $hideLabel])>{{ $label }}@if($required)<span class="required-indicator" aria-hidden="true">*</span>@endif</span>
    <div class="searchable-select">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}_search"
            type="search"
            class="form-field searchable-select__input"
            value="{{ $searchValue }}"
            placeholder="{{ $dependencyDisabled ? $dependencyPlaceholder : $placeholder }}"
            data-default-placeholder="{{ $placeholder }}"
            data-dependency-placeholder="{{ $dependencyPlaceholder }}"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listId }}"
            aria-expanded="false"
            data-admin-person-input
            @disabled($dependencyDisabled)
            @required($required)
        >
        <span class="searchable-select__arrow" aria-hidden="true" @if(filled($searchValue)) hidden @endif data-admin-person-arrow></span>
        <button type="button" class="searchable-select__clear" aria-label="ล้าง{{ $label }}" @if(blank($searchValue)) hidden @endif data-admin-person-clear>×</button>
        <input type="hidden" name="{{ $name }}" value="{{ $selectedValue }}" data-admin-person-value>
        <div id="{{ $listId }}" class="searchable-select__menu admin-person-search__menu" role="listbox" hidden data-admin-person-menu>
            <p class="searchable-select__empty" role="status" data-admin-person-status>พิมพ์อย่างน้อย 2 ตัวอักษรแล้วเลือกบุคลากรจากรายการ</p>
        </div>
    </div>
    @error($name)<span class="admin-field-error">{{ $message }}</span>@enderror
</label>
