@props(['name', 'id', 'accept' => '.pdf', 'form' => null, 'compact' => false, 'compactLabel' => 'อัปโหลดเอกสาร', 'filename' => null, 'required' => false])
<div class="file-upload {{ $compact ? 'file-upload--compact' : '' }} {{ $filename ? 'has-file' : '' }}" data-dropzone>
    <input type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}" @if($form) form="{{ $form }}" @endif @required($required) class="file-upload__input" aria-describedby="{{ $id }}-filename">
    <label class="file-upload__label" for="{{ $id }}">
        @if($compact)
            <x-portal-icon name="upload" />
            <span>
                {{ $compactLabel }}
                @if($required)<span class="required-indicator" aria-hidden="true">*</span>@endif
            </span>
        @else
            <span>
                Drag and Drop
                @if($required)<span class="required-indicator" aria-hidden="true">*</span>@endif
            </span>
            <span class="upload-hint">หรือคลิกเพื่อเลือกไฟล์ PDF (ไม่เกิน 10 MB)</span>
        @endif
    </label>
    <p id="{{ $id }}-filename" class="file-upload__filename" data-file-name aria-live="polite">@if($filename)<img src="{{ asset('img/icons/file-text.svg') }}" alt="" class="portal-icon file-upload__file-icon"><span class="file-upload__file-row"><span class="file-upload__filename-text">{{ $filename }}</span><button type="button" class="file-upload__remove" aria-label="ยกเลิกการเลือกไฟล์">×</button></span>@endif</p>
</div>
