@props(['name', 'id', 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.csv', 'form' => null, 'files' => [], 'maxFiles' => 5, 'studentRosterSelector' => false, 'studentRosterEnabled' => false])
<div class="multi-upload" data-multi-upload data-max-files="{{ $maxFiles }}" data-student-roster-selector="{{ $studentRosterSelector ? 'true' : 'false' }}" data-student-roster-enabled="{{ $studentRosterEnabled ? 'true' : 'false' }}">
    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}[]"
        accept="{{ $accept }}"
        multiple
        @if($form) form="{{ $form }}" @endif
        class="file-upload__input"
        data-multi-upload-input
    >
    <label class="file-upload__label multi-upload__trigger" for="{{ $id }}" data-multi-upload-trigger>
        <x-portal-icon name="upload" />
        <span class="multi-upload__copy">
            <span>เพิ่มเอกสาร</span>
            <small>สูงสุด {{ $maxFiles }} ไฟล์ ไฟล์ละไม่เกิน 10 MB</small>
        </span>
    </label>
    <ul class="multi-upload__list" data-multi-upload-list aria-live="polite">@foreach($files as $index => $file)
            @php($isSpreadsheet = in_array(Illuminate\Support\Str::lower(pathinfo((string) ($file['additional_name'] ?? ''), PATHINFO_EXTENSION)), ['xls', 'xlsx', 'csv'], true))
            <li class="multi-upload__item" data-existing-file>
                <span class="multi-upload__filename">{{ $file['additional_name'] }} @if(!empty($file['additional_size']))({{ number_format($file['additional_size'] / 1024 / 1024, 2) }} MB)@endif</span>
                @if($studentRosterSelector && $isSpreadsheet)
                <label class="multi-upload__roster-choice" data-roster-choice @if(! $studentRosterEnabled) hidden @endif><input type="radio" name="student_roster_file" value="existing:{{ $index }}" @if($form) form="{{ $form }}" @endif @checked($file['additional_is_student_roster'] ?? false)>ใช้เป็นรายชื่อผู้เรียน</label>
                @endif
                <button type="button" class="multi-upload__remove" aria-label="ลบไฟล์ {{ $file['additional_name'] }}" data-remove-existing="{{ $index }}">×</button>
            </li>
        @endforeach</ul>
    <p class="multi-upload__error" data-multi-upload-error aria-live="assertive"></p>
</div>
