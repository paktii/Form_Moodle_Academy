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
                    <h2>ส่วนที่ {{ $step }} : {{ $steps[$step] }}</h2><button class="icon-button" type="button" data-open-dialog="discard-dialog" aria-label="ล้างแบบฟอร์ม"><x-portal-icon name="trash" /></button></div>
                    @endif
                    <div class="form-card__body">
                        @if($step === 1)
                        <x-portal-field name="requester_unit" label="ส่วนงาน/คณะ/สำนักของผู้ยื่น" :value="$value('requester_unit')" readonly />
                        <x-portal-searchable-select name="target_dept_id" label="ส่วนงาน/คณะ/สำนักเป้าหมาย" :options="$departments" :value="$value('target_dept_id')" placeholder="พิมพ์ชื่อส่วนงาน/คณะ/สำนักที่ต้องการสร้างรายวิชาให้" :required="true" />
                        <x-portal-field name="project_name" label="ชื่อโครงการ" :value="$value('project_name')" placeholder="ระบุชื่อโครงการพัฒนาทักษะวิชาการหรือหลักสูตร" :required="true" />
                        <fieldset class="radio-fieldset">
                            <legend>ประเภทโครงการ</legend>
                            <div class="radio-options">@foreach($projectTypes as $typeCode => $typeName)
                                <label><input type="radio" name="project_type" value="{{ $typeCode }}" @checked($value('project_type')===$typeCode) required>{{ $typeName }}</label>
                                @endforeach
                            </div>
                            <input class="form-field conditional-input" name="project_other" value="{{ $value('project_other') }}" placeholder="ระบุประเภทโครงการ" aria-label="ประเภทโครงการอื่น ๆ" data-conditional="project_type" data-when="OTHER" @if($value('project_type') !=='OTHER' ) hidden @endif>
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
                            <x-portal-field name="subject_code" label="รหัสวิชา (ถ้ามี)" :value="$value('subject_code')" placeholder="เช่น CP101" />
                        </div>

                        <section class="instructors-section">
                            <div class="form-section__header">
                                <h3>อาจารย์ผู้สอนหลัก:</h3><x-portal-button variant="secondary" data-add-instructor><x-portal-icon name="plus" />เพิ่มอาจารย์ผู้สอนหลัก</x-portal-button>
                            </div>
                            <div data-instructor-list>
                                @foreach($value('instructors', [['first' => '', 'last' => '', 'email' => '']]) as $index => $instructor)
                                <x-instructor-fields :index="$index" :instructor="$instructor" />
                                @endforeach
                            </div>
                        </section>
                        <fieldset class="radio-fieldset">
                            <legend>หมวดหมู่:</legend>
                            <div class="radio-options">@foreach($categories as $categoryCode => $categoryName)
                                <label><input type="radio" name="category" value="{{ $categoryCode }}" @checked($value('category')===$categoryCode) required>{{ $categoryName }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="form-label" for="description">คำอธิบายรายวิชาโดยย่อ<textarea id="description" name="description" class="form-field form-field--textarea" placeholder="ระบุเนื้อหาหลักหรือวัตถุประสงค์โดยสังเขปเพื่อเป็นข้อมูลในการอนุมัติ" required>{{ $value('description') }}</textarea></label>
                        @elseif($step === 3)
                        <fieldset class="radio-fieldset">
                            <legend>รูปแบบการเปิดสอน:</legend>
                            <div class="radio-options">@foreach(['แบบเรียนรู้ตามอัธยาศัยตลอดเวลา', 'แบบกำหนดช่วงเวลาเรียน'] as $learning)
                                <label><input type="radio" name="learning" value="{{ $learning }}" @checked($value('learning')===$learning) required>{{ $learning }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset class="radio-fieldset" data-learning-period @if($value('learning') !=='แบบกำหนดช่วงเวลาเรียน' ) hidden @endif>
                            <legend>ระยะเวลาเปิด-ปิดรายวิชา:</legend>
                            <div class="form-grid">
                                <x-portal-field name="starts_at" label="วันที่เริ่มเปิดเรียน:" type="date" :value="$value('starts_at')" :required="$value('learning') === 'แบบกำหนดช่วงเวลาเรียน'" />
                                <x-portal-field name="ends_at" label="ถึง วันที่สิ้นสุดการเรียน:" type="date" :value="$value('ends_at')" :required="$value('learning') === 'แบบกำหนดช่วงเวลาเรียน'" />
                            </div>
                        </fieldset>
                        <fieldset class="radio-fieldset">
                            <legend>รูปแบบการเข้ารายวิชา:</legend>
                            <div class="radio-options">@foreach(['ใช้รหัสผ่าน (Enrollment Key)', 'ผู้ดูแลระบบนำเข้ารายชื่อ', 'อื่น ๆ (ระบุ)'] as $enrollment)
                                <label><input type="radio" name="enrollment" value="{{ $enrollment }}" @checked($value('enrollment')===$enrollment) required>{{ $enrollment }}</label>
                                @endforeach
                            </div>
                            <input name="enrollment_other" value="{{ $value('enrollment_other') }}" class="form-field conditional-input" aria-label="รูปแบบการเข้ารายวิชาอื่น ๆ" data-conditional="enrollment" data-when="อื่น ๆ (ระบุ)" @if($value('enrollment') !=='อื่น ๆ (ระบุ)' ) hidden @endif>
                        </fieldset>
                        <div class="expected-students"><x-portal-field name="expected_students" label="จำนวนผู้เรียนที่คาดการณ์:" type="number" min="1" :value="$value('expected_students')" :required="true" /><span>คน</span></div>
                        @else
                        <section class="document-download">
                            <div class="form-section__header">
                                <h2>ดาวน์โหลดเอกสารเพื่อลงนาม</h2><span class="muted">ทั้งหมด 1 ไฟล์</span>
                            </div>
                            <div class="document-row"><x-portal-icon name="download" />
                                <div class="document-row__name">{{ $data['project_name'] ?? 'โครงการขออนุมัติ' }}.pdf</div><x-portal-button variant="secondary" data-open-dialog="pdf-preview-dialog">Preview</x-portal-button><x-portal-button :href="route('requests.document.download', 'draft')" variant="secondary"><x-portal-icon name="download" />ดาวน์โหลด</x-portal-button>
                            </div>
                        </section>
                        <section class="signed-upload">
                            <h2>อัปโหลดเอกสารที่ลงนามแล้ว</h2><x-portal-upload name="signed_document" id="signed-document" />
                        </section>
                        @endif
                    </div>
            </section>
            <div class="form-actions">
                <x-portal-button type="submit" name="navigation" value="back" variant="secondary" formnovalidate>ย้อนกลับ</x-portal-button>
                <x-portal-button type="submit" name="navigation" value="next" variant="secondary">{{ $step < 4 ? 'ถัดไป' : 'เสร็จสิ้น' }}</x-portal-button>
            </div>
        </form>
        <x-portal-stepper :step="$step" :steps="$steps" :data="$data" />
    </main>
    <x-portal-modal id="discard-dialog" title="ล้างข้อมูลส่วนที่ {{ $step }}">
        <p>ต้องการล้างข้อมูลที่กรอกไว้ในส่วนนี้ใช่หรือไม่?</p>
        <form method="post" action="{{ route('requests.clear-step', $step) }}">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยัน</x-portal-button></div></form>
    </x-portal-modal>
    <x-portal-modal id="pdf-preview-dialog" title="ตัวอย่างเอกสาร">
        <iframe src="{{ route('requests.document', ['id' => 'draft', 'preview' => '1']) }}" class="pdf-preview-frame" title="ตัวอย่างเอกสาร PDF"></iframe>
    </x-portal-modal>
</x-portal-layout>
