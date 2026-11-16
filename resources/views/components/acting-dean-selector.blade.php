@props(['checked' => false, 'buasriId' => null, 'personName' => null])
@php
    $isChecked = (bool) old('dean_is_acting', $checked);
    $selectedBuasriId = old('acting_dean_buasri_id', $buasriId ?? '');
    $selectedPersonName = old('acting_dean_buasri_id_search', $personName ?? '');
@endphp
<div class="acting-dean" data-acting-dean>
    <label class="acting-dean__toggle">
        <input type="hidden" name="dean_is_acting" value="0">
        <input type="checkbox" name="dean_is_acting" value="1" @checked($isChecked) data-acting-dean-toggle>
        <span>ใช้ผู้รักษาการแทน</span>
    </label>
    <div class="acting-dean__search" data-acting-dean-search>
        <x-admin-person-search
            name="acting_dean_buasri_id"
            label="ผู้รักษาการแทน"
            :search-url="route('requests.personnel.search')"
            :value="$selectedBuasriId"
            :search-value="$selectedPersonName"
            :required="$isChecked"
        />
        <p class="acting-dean__hint">อีเมลและลิงก์พิจารณาคำร้องจะส่งให้ผู้รักษาการแทนที่เลือก</p>
    </div>
</div>
