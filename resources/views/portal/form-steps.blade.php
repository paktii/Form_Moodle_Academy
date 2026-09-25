@php
$value = fn ($key, $default = '') => old($key, $data[$key] ?? $default);
@endphp
<x-portal-layout title="สร้างคำร้อง">
    <main class="form-layout">
        <form id="course-form" action="{{ route('requests.save-step', $step) }}" method="post" enctype="multipart/form-data">
            @csrf
            <section class="form-card">
                @if($step < 4)
                    <div class="form-card__heading">
                    <h2>ส่วนที่ {{ $step }} : {{ $steps[(int) $step] ?? '' }}</h2><button class="icon-button" type="button" data-open-dialog="discard-dialog" aria-label="ล้างแบบฟอร์ม"><x-portal-icon name="trash" /></button></div>
                    @endif
                    <div class="form-card__body">
                        @if($step === 1)
                        <x-portal-field name="requester_unit" label="ส่วนงาน/คณะ/สำนักของผู้ยื่น" :value="$value('requester_unit')" readonly />
                        <x-portal-searchable-select name="target_dept_id" label="ส่วนงาน/คณะ/สำนักเป้าหมาย" :options="$departments" :value="$value('target_dept_id')" placeholder="พิมพ์ชื่อส่วนงาน/คณะ/สำนักที่ต้องการสร้างรายวิชาให้" :required="true" />
                        <x-portal-field name="project_name" label="ชื่อโครงการ" :value="$value('project_name')" placeholder="ระบุชื่อโครงการพัฒนาทักษะวิชาการหรือหลักสูตร" :required="true" />
                        <fieldset class="radio-fieldset">
                            <legend>ประเภทโครงการ<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">@foreach($projectTypes as $typeCode => $typeName)
                                <label><input type="radio" name="project_type" value="{{ $typeCode }}" @checked($value('project_type')===$typeCode) required>{{ $typeName }}</label>
                                @endforeach
                            </div>
                            <label class="form-label form-label--conditional" data-conditional="project_type" data-when="OTHER" @if($value('project_type') !=='OTHER' ) hidden @endif>ระบุประเภทโครงการ<span class="required-indicator" aria-hidden="true">*</span><input class="form-field conditional-input" name="project_other" value="{{ $value('project_other') }}" placeholder="ระบุประเภทโครงการ" required></label>
                        </fieldset>
                        <div class="form-grid">
                            <x-portal-field name="coordinator_first" label="ชื่อผู้ประสานงาน" :value="$value('coordinator_first')" placeholder="ระบุชื่อผู้ประสานงาน" :required="true" />
                            <x-portal-field name="coordinator_last" label="นามสกุลผู้ประสานงาน" :value="$value('coordinator_last')" placeholder="ระบุนามสกุลผู้ประสานงาน" :required="true" />
                        </div>
                        <x-portal-field name="coordinator_position" label="ตำแหน่ง" :value="$value('coordinator_position')" placeholder="ระบุตำแหน่งในโครงการ" :required="true" />
                        <div class="form-grid">
                            <x-portal-field name="coordinator_phone" label="เบอร์โทรศัพท์ติดต่อ / ภายใน" :value="$value('coordinator_phone')" placeholder="เช่น 02-649-5000 ต่อ 15052" type="tel" :required="true" />
                            <x-portal-field name="coordinator_email" label="E-mail (มหาวิทยาลัย)" :value="$value('coordinator_email')" placeholder="เช่น example@g.swu.ac.th" type="email" :required="true" />
                        </div>
                        @elseif($step === 2)
                        <div class="form-grid">
                            <x-portal-field name="course_th" label="ชื่อรายวิชา (ภาษาไทย)" :value="$value('course_th')" placeholder="เช่น การเขียนโปรแกรมคอมพิวเตอร์เบื้องต้น" :required="true" />
                            <x-portal-field name="course_en" label="Course Title (English)" :value="$value('course_en')" placeholder="e.g., Introduction to Computer Programming" :required="true" />
                        </div>

                        <section class="instructors-section">
                            <div class="form-section__header">
                                <h3>อาจารย์ผู้สอน:</h3><x-portal-button variant="secondary" data-add-instructor><x-portal-icon name="plus" />เพิ่มอาจารย์ผู้สอน</x-portal-button>
                            </div>
                            <div data-instructor-list>
                                @foreach($value('instructors', [['first' => '', 'last' => '', 'email' => '']]) as $index => $instructor)
                                <x-instructor-fields :index="$index" :instructor="$instructor" />
                                @endforeach
                            </div>
                        </section>
                        <fieldset class="radio-fieldset category-fieldset">
                            <legend>หมวดหมู่:<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">@foreach($categories as $categoryCode => $categoryName)
                                <label><input type="radio" name="category" value="{{ $categoryCode }}" @checked($value('category')===$categoryCode) required>{{ $categoryName }}</label>
                                @endforeach
                            </div>
                            <label class="form-label form-label--conditional" data-conditional="category" data-when="OTHER" @if($value('category') !=='OTHER' ) hidden @endif>ระบุหมวดหมู่อื่น ๆ<span class="required-indicator" aria-hidden="true">*</span><input name="category_other" value="{{ $value('category_other') }}" class="form-field conditional-input" placeholder="ระบุหมวดหมู่อื่น ๆ" required></label>
                        </fieldset>
                        <label class="form-label" for="description">คำอธิบายรายวิชาโดยย่อ<span class="required-indicator" aria-hidden="true">*</span><textarea id="description" name="description" class="form-field form-field--textarea" placeholder="ระบุเนื้อหาหลักหรือวัตถุประสงค์โดยสังเขปเพื่อเป็นข้อมูลในการอนุมัติ" required>{{ $value('description') }}</textarea></label>
                        @elseif($step === 3)
                        <fieldset class="radio-fieldset">
                            <legend>ลักษณะการดำเนินกิจกรรม:<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">@foreach(['เปิดแบบตามวงรอบ (Phase/Batch-based)', 'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)'] as $learning)
                                <label><input type="radio" name="learning" value="{{ $learning }}" @checked($value('learning')===$learning) required>@if(str_contains($learning, '(Event / Project-based)'))<span>แบบเปิดตามกรอบระยะเวลาของโครงการ<span class="learning-label-sub">(Event / Project-based)</span></span>@else{{ $learning }}@endif</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="form-grid" data-activity-details>
                            <x-portal-field name="activity_round" label="วงรอบ/รุ่นที่" :value="$value('activity_round')" placeholder="เช่น รุ่นที่ 1" :required="$value('learning') === 'เปิดแบบตามวงรอบ (Phase/Batch-based)'" :disabled="$value('learning') === 'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)'" />
                            <x-portal-field name="activity_phase" label="เฟส" :value="$value('activity_phase')" placeholder="เช่น เฟส 1/2569" :required="$value('learning') === 'เปิดแบบตามวงรอบ (Phase/Batch-based)'" :disabled="$value('learning') === 'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)'" />
                        </div>
                        <fieldset class="radio-fieldset">
                            <legend>ระยะเวลาดำเนินกิจกรรม:</legend>
                            <div class="form-grid">
                                <x-portal-field name="starts_at" label="วันที่เริ่มต้น:" type="date" :value="$value('starts_at')" :required="true" min="{{ date('Y-m-d') }}" />
                                <x-portal-field name="ends_at" label="ถึง วันที่สิ้นสุด:" type="date" :value="$value('ends_at')" :required="true" min="{{ date('Y-m-d') }}" />
                            </div>
                        </fieldset>
                        <fieldset class="radio-fieldset">
                            <legend>รูปแบบการเข้ารายวิชา:<span class="required-indicator" aria-hidden="true">*</span></legend>
                            <div class="radio-options">@foreach(['ใช้รหัสผ่าน (Enrollment Key)', 'ผู้ดูแลระบบนำเข้ารายชื่อ', 'อื่น ๆ (ระบุ)'] as $enrollment)
                                <label><input type="radio" name="enrollment" value="{{ $enrollment }}" @checked($value('enrollment')===$enrollment) required>{{ $enrollment }}</label>
                                @endforeach
                            </div>
                            <p class="enrollment-import-note" data-conditional="enrollment" data-when="ผู้ดูแลระบบนำเข้ารายชื่อ" aria-live="polite" @if($value('enrollment') !=='ผู้ดูแลระบบนำเข้ารายชื่อ' ) hidden @endif>*กรุณาแนบรายชื่อในช่องเอกสารเพิ่มเติม <br class="enrollment-import-note__mobile-break">หากยังไม่มีรายชื่อกรุณาแนบมาภายหลัง</p>
                            <label class="form-label form-label--conditional" data-conditional="enrollment" data-when="อื่น ๆ (ระบุ)" @if($value('enrollment') !=='อื่น ๆ (ระบุ)' ) hidden @endif>ระบุรูปแบบการเข้ารายวิชาอื่น ๆ<span class="required-indicator" aria-hidden="true">*</span><input name="enrollment_other" value="{{ $value('enrollment_other') }}" class="form-field conditional-input" placeholder="ระบุรูปแบบการเข้ารายวิชา" required></label>
                        </fieldset>
                        <div class="expected-students"><x-portal-field name="expected_students" label="จำนวนผู้เรียนที่คาดการณ์:" type="number" min="1" :value="$value('expected_students')" :required="true" /><span>คน</span></div>
                        @else
                        <section class="document-download">
                            <div class="form-section__header">
                                <h2>ดาวน์โหลดเอกสารเพื่อลงนาม</h2><span class="muted">ทั้งหมด 1 ไฟล์</span>
                            </div>
                            <div class="document-row"><x-portal-icon name="download" />
                                <div class="document-row__name">{{ $data['project_name'] ?? 'โครงการขออนุมัติ' }}.pdf</div><div class="document-row__actions"><x-portal-button variant="secondary" class="document-row__icon-button" data-open-dialog="pdf-preview-dialog" aria-label="Preview เอกสาร" title="Preview เอกสาร"><x-portal-icon name="eye" /></x-portal-button><x-portal-button :href="route('requests.document.download', 'draft')" variant="secondary" class="document-row__icon-button" aria-label="ดาวน์โหลดเอกสาร" title="ดาวน์โหลดเอกสาร"><x-portal-icon name="download" /></x-portal-button></div>
                            </div>
                        </section>
                        <section class="signed-upload">
                            <h2>อัปโหลดเอกสารที่ลงนามแล้ว</h2>
                            <p class="muted">สามารถอัปโหลดเอกสารที่ลงนามแล้วในภายหลังได้</p>
                            <x-portal-upload name="signed_document" id="signed-document" />
                        </section>
                        @endif
                    </div>
            </section>
            <div class="form-actions">
                <x-portal-button type="submit" name="navigation" value="back" variant="secondary" formnovalidate>ย้อนกลับ</x-portal-button>
                @if($step < 4)
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
        <form method="post" action="{{ route('requests.clear-step', $step) }}">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยัน</x-portal-button></div>
        </form>
    </x-portal-modal>
    <x-portal-modal id="pdf-preview-dialog" title="ตัวอย่างเอกสาร">
        <div class="pdf-preview-viewer" data-pdf-preview data-pdf-url="{{ route('requests.document', ['id' => 'draft', 'preview' => '1']) }}" role="region" aria-label="ตัวอย่างเอกสาร PDF" aria-live="polite">
            <p class="pdf-preview-status">กำลังโหลดตัวอย่างเอกสาร...</p>
        </div>
    </x-portal-modal>
    @if($step === 4)
    <x-portal-modal id="finish-dialog" title="ยืนยันการบันทึกคำร้อง">
        <p data-finish-dialog-text style="text-align: center; font-size: 16px; line-height: 1.6;">คุณต้องการบันทึกคำร้องนี้ใช่หรือไม่?<br>หลังจากบันทึกแล้ว <span class="finish-dialog__mobile-line">ระบบจะนำคุณไปยังหน้ารายการคำร้อง</span><br class="finish-dialog__desktop-break">เพื่อรอการอัปโหลดเอกสารที่ลงนามแล้วในภายหลัง</p>
        <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" form="course-form" name="navigation" value="next" variant="primary"><span data-finish-confirm-label>ยืนยันการบันทึก</span></x-portal-button></div>
    </x-portal-modal>
    @endif
</x-portal-layout>
