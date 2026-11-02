@php
$value = fn ($key, $default = '') => old($key, $data[$key] ?? $default);
$requestNumber = (string) ($data['request_no'] ?? $data['number'] ?? '');
$requestCode = \Illuminate\Support\Str::afterLast($requestNumber, '-');
$documentDownloadFilename = $requestCode !== '' ? "CC-ITED-05_{$requestCode}.pdf" : 'CC-ITED-05.pdf';
@endphp
<x-portal-layout :title="$formName" header-context="สร้างคำร้อง" :highlight-header-context="true" :tab-title="'e-Meeting Administrator · Step '.$step">
    <main class="form-layout">
        <form id="course-form" action="{{ route('emeeting-requests.save-step', $step) }}" method="post" enctype="multipart/form-data" data-request-form>
            @csrf
            <section class="form-card">
                @if($step < 3)
                <div class="form-card__heading"><h2>ส่วนที่ {{ $step }} : {{ $steps[(int) $step] ?? '' }}</h2><button class="icon-button" type="button" data-open-dialog="discard-dialog" aria-label="ล้างแบบฟอร์ม"><x-portal-icon name="trash" /></button></div>
                @endif
                <div class="form-card__body {{ $step === 1 ? 'form-card__body--step-one' : '' }}">
                    @if($step === 1)
                        <x-portal-searchable-select name="target_dept_id" label="ชื่อส่วนงาน/คณะ/สำนัก" :options="$departments" :value="$value('target_dept_id')" placeholder="พิมพ์ชื่อส่วนงาน/คณะ/สำนัก" :required="true" />
                        <x-portal-searchable-select name="target_subdept_id" label="หน่วยงานย่อย (ถ้ามี)" :options="$subdepartments" :option-parents="$subdepartmentParents" :value="$value('target_subdept_id')" depends-on="target_dept_id" :parent-value="$value('target_dept_id')" placeholder="พิมพ์ชื่อหน่วยงานย่อย" />
                        <fieldset class="radio-fieldset emeeting-service-type">
                            <legend>ขอรับบริการ<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">
                                <label><input type="radio" name="action_type" value="NEW_ACCESS" @checked($value('action_type') === 'NEW_ACCESS') required>ขอสิทธิ์ผู้ดูแล</label>
                                <label><input type="radio" name="action_type" value="CHANGE_CONTACT" @checked($value('action_type') === 'CHANGE_CONTACT') required>เปลี่ยนแปลงสิทธิ์ผู้ดูแล</label>
                            </div>
                            <section class="emeeting-current-contact" data-conditional="action_type" data-when="CHANGE_CONTACT" @if($value('action_type') !== 'CHANGE_CONTACT') hidden @endif>
                                <h3 class="website-contact-change-subheading">ผู้ดูแลเดิม</h3>
                                <div class="form-grid">
                                    <x-portal-field name="current_contact_first" label="ชื่อ" :value="$value('current_contact_first')" placeholder="ระบุชื่อผู้ดูแลเดิม" :required="true" />
                                    <x-portal-field name="current_contact_last" label="นามสกุล" :value="$value('current_contact_last')" placeholder="ระบุนามสกุลผู้ดูแลเดิม" :required="true" />
                                </div>
                            </section>
                        </fieldset>
                    @elseif($step === 2)
                        <h3 class="website-contact-change-subheading">ผู้ดูแลกรอบการประชุมที่จะได้รับสิทธิ์</h3>
                        <div class="form-grid">
                            <x-portal-field name="contact_first" label="ชื่อ" :value="$value('contact_first')" placeholder="ระบุชื่อ" :required="true" />
                            <x-portal-field name="contact_last" label="นามสกุล" :value="$value('contact_last')" placeholder="ระบุนามสกุล" :required="true" />
                        </div>
                        <x-portal-field name="contact_position" label="ตำแหน่ง" :value="$value('contact_position')" placeholder="ระบุตำแหน่ง" :required="true" />
                        <div class="form-grid">
                            <x-portal-field name="contact_email" label="E-mail (มหาวิทยาลัย)" :value="$value('contact_email')" placeholder="เช่น example@g.swu.ac.th" type="email" :required="true" />
                            <x-portal-field name="contact_phone" label="โทรศัพท์ติดต่อ / ภายใน" :value="$value('contact_phone')" placeholder="เช่น 02-649-5000 ต่อ 17977" type="tel" :required="true" />
                        </div>
                        <section class="website-certification" aria-labelledby="emeeting-agreement-title">
                            <h3 id="emeeting-agreement-title">ข้อตกลง</h3>
                            <ol>
                                <li>ผู้ดูแลกรอบการประชุม (Framework Administrator) คือผู้ดูแลกรอบการประชุมทั้งหมดในระบบการประชุมอิเล็กทรอนิกส์ มศว (SWU e-Meeting) ประจำส่วนงาน โดยแต่ละส่วนงานกำหนดให้มีผู้ดูแลกรอบการประชุมได้เพียง 1 ท่านเท่านั้น</li>
                                <li>กรณีขอเปลี่ยนแปลงสิทธิ์ผู้ดูแลกรอบการประชุม สำนักคอมพิวเตอร์จะดำเนินการยกเลิกผู้ดูแลเดิมของส่วนงานทันที</li>
                            </ol>
                        </section>
                    @else
                        <section class="dean-selection"><div class="form-section__header"><h2>เลือกผู้รับรองของส่วนงาน</h2></div><x-portal-searchable-select name="dean_pers_id" label="คณบดี / ผู้อำนวยการ / หัวหน้าส่วนงาน" :options="$deanOptions" :value="$value('dean_pers_id')" placeholder="พิมพ์ชื่อผู้รับรองที่ต้องการส่งคำร้องให้" :required="!empty($deanOptions)" :show-all-on-focus="true" />@error('dean_pers_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror</section>
                        <section class="document-download"><div class="form-section__header"><h2>ตรวจสอบเอกสารคำร้อง</h2><span class="muted">ทั้งหมด 1 ไฟล์</span></div><div class="document-row"><x-portal-icon name="download" /><div class="document-row__name">{{ $documentDownloadFilename }}</div><div class="document-row__actions"><x-portal-button variant="secondary" class="document-row__icon-button" data-open-dialog="pdf-preview-dialog" aria-label="Preview เอกสาร" title="Preview เอกสาร"><x-portal-icon name="eye" /></x-portal-button><x-portal-button :href="route('emeeting-requests.document.download', 'draft')" variant="secondary" class="document-row__icon-button" :download="$documentDownloadFilename" aria-label="ดาวน์โหลดเอกสาร" title="ดาวน์โหลดเอกสาร"><x-portal-icon name="download" /></x-portal-button></div></div></section>
                        <section class="document-attachments"><h2>เอกสารเพิ่มเติมที่แนบมา</h2><div class="document-attachments__content" data-additional-documents-content><p class="muted document-attachments__empty" data-additional-document-empty>ไม่มีเอกสารเพิ่มเติมที่แนบมา</p><div data-additional-document-list></div></div></section>
                    @endif
                </div>
            </section>
            <div class="form-actions"><x-portal-button type="submit" name="navigation" value="back" variant="secondary" formnovalidate>ย้อนกลับ</x-portal-button>@if($step < 3)<x-portal-button type="submit" name="navigation" value="next" variant="secondary">ถัดไป</x-portal-button>@else<x-portal-button type="button" data-finish-request variant="secondary">เสร็จสิ้น</x-portal-button>@endif</div>
        </form>
        <x-portal-stepper :step="$step" :steps="$steps" :data="$data" />
    </main>
    <x-portal-modal id="discard-dialog" title="ล้างข้อมูลส่วนที่ {{ $step }}"><p>ต้องการล้างข้อมูลที่กรอกไว้ในส่วนนี้ใช่หรือไม่?</p><form method="post" action="{{ route('emeeting-requests.clear-step', $step) }}">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยัน</x-portal-button></div></form></x-portal-modal>
    <x-portal-modal id="pdf-preview-dialog" title="ตัวอย่างเอกสาร"><div class="pdf-preview-viewer" data-pdf-preview data-pdf-url="{{ route('emeeting-requests.document', ['id' => 'draft', 'preview' => '1']) }}" role="region" aria-label="ตัวอย่างเอกสาร PDF" aria-live="polite"><p class="pdf-preview-status">กำลังโหลดตัวอย่างเอกสาร...</p></div></x-portal-modal>
    @if($step === 3)<x-portal-modal id="finish-dialog" title="ยืนยันการบันทึกคำร้อง"><p class="confirmation-message">คุณต้องการส่งคำร้องนี้ให้ผู้รับรองที่เลือกใช่หรือไม่?<br>หลังจากบันทึกแล้ว คำร้องจะอยู่ในสถานะรอการรับรอง</p><div class="modal-actions"><x-portal-button type="button" variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" form="course-form" name="navigation" value="next" variant="primary">ยืนยันการส่งคำร้อง</x-portal-button></div></x-portal-modal>@endif
</x-portal-layout>
