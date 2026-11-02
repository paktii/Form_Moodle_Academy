@php
$value = fn ($key, $default = '') => old($key, $data[$key] ?? $default);
$requestNumber = (string) ($data['request_no'] ?? $data['number'] ?? '');
$requestCode = \Illuminate\Support\Str::afterLast($requestNumber, '-');
$documentDownloadFilename = $requestCode !== ''
    ? "CC-ITED-01_{$requestCode}.pdf"
    : (($data['website_name'] ?? 'คำขอใช้พื้นที่เว็บไซต์').'.pdf');
@endphp
<x-portal-layout :title="$formName" header-context="สร้างคำร้อง" :highlight-header-context="true" :tab-title="'New Website Request · Step '.$step">
    <main class="form-layout">
        <form id="course-form" action="{{ route('website-requests.save-step', $step) }}" method="post" enctype="multipart/form-data" data-request-form>
            @csrf
            <section class="form-card">
                @if($step < 3)
                <div class="form-card__heading">
                    <h2>ส่วนที่ {{ $step }} : {{ $steps[(int) $step] ?? '' }}</h2>
                    <button class="icon-button" type="button" data-open-dialog="discard-dialog" aria-label="ล้างแบบฟอร์ม"><x-portal-icon name="trash" /></button>
                </div>
                @endif
                <div class="form-card__body {{ $step === 1 ? 'form-card__body--step-one' : '' }} {{ $step === 2 ? 'form-card__body--website-contact' : '' }}">
                    @if($step === 1)
                        <x-portal-searchable-select name="target_dept_id" label="ชื่อส่วนงาน/คณะ/สำนัก" :options="$departments" :value="$value('target_dept_id')" placeholder="พิมพ์ชื่อส่วนงาน/คณะ/สำนักที่ขอใช้พื้นที่เว็บไซต์" :required="true" />
                        <x-portal-searchable-select name="target_subdept_id" label="หน่วยงานย่อย (ถ้ามี)" :options="$subdepartments" :option-parents="$subdepartmentParents" :value="$value('target_subdept_id')" depends-on="target_dept_id" :parent-value="$value('target_dept_id')" placeholder="พิมพ์ชื่อหน่วยงานย่อย" />
                        <x-portal-field name="website_name" label="ชื่อเว็บไซต์" :value="$value('website_name')" placeholder="ระบุชื่อเว็บไซต์" :required="true" />

                        <fieldset class="radio-fieldset website-three-column-options">
                            <legend>ประเภทการขอใช้พื้นที่<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">
                                @foreach(['DEPARTMENT' => 'เว็บไซต์ส่วนงาน', 'PROJECT' => 'เว็บไซต์โครงการ', 'CONFERENCE' => 'เว็บไซต์ประชุมวิชาการ'] as $scopeCode => $scopeLabel)
                                <label><input type="radio" name="site_scope_type" value="{{ $scopeCode }}" @checked($value('site_scope_type') === $scopeCode) required>{{ $scopeLabel }}</label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="form-grid" data-website-period @if($value('site_scope_type') === 'DEPARTMENT' || $value('site_scope_type') === '') hidden @endif>
                            <x-portal-field name="service_starts_on" label="วันที่เริ่มต้น" type="date" :value="$value('service_starts_on')" />
                            <x-portal-field name="service_ends_on" label="วันที่สิ้นสุด" type="date" :value="$value('service_ends_on')" />
                        </div>

                        <x-portal-field name="domain_url" label="โดเมน (URL) ที่ต้องการ" :value="$value('domain_url')" placeholder="เช่น project.swu.ac.th" :required="true" />

                        <fieldset class="radio-fieldset website-three-column-options">
                            <legend>รายละเอียดเว็บไซต์<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">
                                @foreach($platforms as $platformCode => $platformName)
                                <label><input type="radio" name="platform_code" value="{{ $platformCode }}" @checked($value('platform_code') === $platformCode) required>{{ $platformName }}</label>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="form-label" for="field-additional-detail">รายละเอียดเพิ่มเติม (ถ้ามี)
                            <textarea id="field-additional-detail" name="additional_detail" class="form-field form-field--textarea @error('additional_detail') form-field--error @enderror" rows="4" maxlength="5000" placeholder="ระบุรายละเอียดหรือความต้องการเพิ่มเติม">{{ $value('additional_detail') }}</textarea>
                            @error('additional_detail')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                        </label>
                    @elseif($step === 2)
                        <div class="form-grid">
                            <x-portal-field name="contact_first" label="ชื่อผู้ดูแลเว็บไซต์" :value="$value('contact_first')" placeholder="ระบุชื่อ" :required="true" />
                            <x-portal-field name="contact_last" label="นามสกุลผู้ดูแลเว็บไซต์" :value="$value('contact_last')" placeholder="ระบุนามสกุล" :required="true" />
                        </div>
                        <x-portal-field name="contact_position" label="ตำแหน่ง" :value="$value('contact_position')" placeholder="ระบุตำแหน่ง" :required="true" />
                        <div class="form-grid">
                            <x-portal-field name="contact_email" label="E-mail (มหาวิทยาลัย)" :value="$value('contact_email')" placeholder="เช่น example@g.swu.ac.th" type="email" :required="true" />
                            <x-portal-field name="contact_phone" label="โทรศัพท์ติดต่อ / ภายใน" :value="$value('contact_phone')" placeholder="เช่น 02-649-5000 ต่อ 15044" type="tel" :required="true" />
                        </div>
                        <section class="website-certification" aria-labelledby="website-certification-title">
                            <h3 id="website-certification-title">คำรับรอง</h3>
                            <p>ข้าพเจ้าขอรับรองว่าจะปฏิบัติตามกฏระเบียบ คำแนะนำ และประกาศต่าง ๆ เกี่ยวกับการใช้บริการเครือข่ายบัวศรี ซึ่งสำนักคอมพิวเตอร์กำหนดไว้และที่จะประกาศเพิ่มเติมในอนาคต ดังนี้</p>
                            <ol>
                                <li>ไม่ยอมให้ผู้อื่นมาร่วมใช้บัญชีสำหรับผู้ดูแลเว็บไซต์ของหน่วยงานตามที่ได้รับจากสำนักคอมพิวเตอร์</li>
                                <li>ไม่ใช้บริการนี้ไปในทางเสื่อมเสีย หรือทำให้ผู้อื่นถูกละเมิดหรือได้รับความเดือดร้อน</li>
                                <li>ไม่ใช้บริการนี้เพื่อผลประโยชน์ทางการค้าทั้งเพื่อตนเอง หรือเพื่อบุคคลอื่นใดทั้งสิ้น</li>
                            </ol>
                            <p>ข้าพเจ้าจะรับผิดชอบในการนำแฟ้มข้อมูลต่างๆ ไปเก็บบน Web Server ของมหาวิทยาลัยใน Directory ของส่วนงานตามที่ได้รับมอบหมาย</p>
                        </section>
                    @else
                        <section class="dean-selection">
                            <div class="form-section__header"><h2>เลือกผู้รับรองของส่วนงาน</h2></div>
                            <x-portal-searchable-select name="dean_pers_id" label="คณบดี / ผู้อำนวยการ / หัวหน้าส่วนงาน" :options="$deanOptions" :value="$value('dean_pers_id')" placeholder="พิมพ์ชื่อผู้รับรองที่ต้องการส่งคำร้องให้" :required="!empty($deanOptions)" :show-all-on-focus="true" />
                            @error('dean_pers_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </section>
                        <section class="document-download">
                            <div class="form-section__header"><h2>ตรวจสอบเอกสารคำร้อง</h2><span class="muted">ทั้งหมด 1 ไฟล์</span></div>
                            <div class="document-row"><x-portal-icon name="download" />
                                <div class="document-row__name">{{ $documentDownloadFilename }}</div>
                                <div class="document-row__actions">
                                    <x-portal-button variant="secondary" class="document-row__icon-button" data-open-dialog="pdf-preview-dialog" aria-label="Preview เอกสาร" title="Preview เอกสาร"><x-portal-icon name="eye" /></x-portal-button>
                                    <x-portal-button :href="route('website-requests.document.download', 'draft')" variant="secondary" class="document-row__icon-button" :download="$documentDownloadFilename" aria-label="ดาวน์โหลดเอกสาร" title="ดาวน์โหลดเอกสาร"><x-portal-icon name="download" /></x-portal-button>
                                </div>
                            </div>
                        </section>
                        <section class="document-attachments">
                            <h2>เอกสารเพิ่มเติมที่แนบมา</h2>
                            <div class="document-attachments__content" data-additional-documents-content>
                                <p class="muted document-attachments__empty" data-additional-document-empty>ไม่มีเอกสารเพิ่มเติมที่แนบมา</p>
                                <div data-additional-document-list></div>
                            </div>
                        </section>
                    @endif
                </div>
            </section>
            <div class="form-actions">
                <x-portal-button type="submit" name="navigation" value="back" variant="secondary" formnovalidate>ย้อนกลับ</x-portal-button>
                @if($step < 3)
                    <x-portal-button type="submit" name="navigation" value="next" variant="secondary">ถัดไป</x-portal-button>
                @else
                    <x-portal-button type="button" data-finish-request variant="secondary">เสร็จสิ้น</x-portal-button>
                @endif
            </div>
        </form>
        <x-portal-stepper :step="$step" :steps="$steps" :data="$data" />
    </main>

    <x-portal-modal id="discard-dialog" title="ล้างข้อมูลส่วนที่ {{ $step }}">
        <p>ต้องการล้างข้อมูลที่กรอกไว้ในส่วนนี้ใช่หรือไม่?</p>
        <form method="post" action="{{ route('website-requests.clear-step', $step) }}">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยัน</x-portal-button></div></form>
    </x-portal-modal>
    <x-portal-modal id="pdf-preview-dialog" title="ตัวอย่างเอกสาร">
        <div class="pdf-preview-viewer" data-pdf-preview data-pdf-url="{{ route('website-requests.document', ['id' => 'draft', 'preview' => '1']) }}" role="region" aria-label="ตัวอย่างเอกสาร PDF" aria-live="polite"><p class="pdf-preview-status">กำลังโหลดตัวอย่างเอกสาร...</p></div>
    </x-portal-modal>
    @if($step === 3)
    <x-portal-modal id="finish-dialog" title="ยืนยันการบันทึกคำร้อง" class="website-finish-dialog">
        <p class="confirmation-message">คุณต้องการส่งคำร้องนี้ให้ผู้รับรองที่เลือกใช่หรือไม่?<br>หลังจากบันทึกแล้ว คำร้องจะอยู่ในสถานะรอการรับรอง</p>
        <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" form="course-form" name="navigation" value="next" variant="primary">ยืนยันการส่งคำร้อง</x-portal-button></div>
    </x-portal-modal>
    @endif
</x-portal-layout>
