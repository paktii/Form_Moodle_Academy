@props(['name', 'id', 'accept' => '.pdf', 'form' => null, 'compact' => false, 'filename' => null, 'required' => false])
<div class="file-upload {{ $compact ? 'file-upload--compact' : '' }}" data-dropzone>
    <input type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}" @if($form) form="{{ $form }}" @endif @required($required) class="file-upload__input" aria-describedby="{{ $id }}-filename">
    <label class="file-upload__label" for="{{ $id }}">
        @if($compact)<x-portal-icon name="upload" /><span>อัปโหลดเอกสาร</span>@else<span>Drag and Drop</span><span class="upload-hint">หรือคลิกเพื่อเลือกไฟล์ PDF (ไม่เกิน 10 MB)</span>@endif
    </label>
    <p id="{{ $id }}-filename" class="file-upload__filename" data-file-name aria-live="polite">{{ $filename }}</p>
</div>