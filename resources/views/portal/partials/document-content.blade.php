@php
$text = fn (string $key, string $fallback = '') => filled($record[$key] ?? null) ? $record[$key] : $fallback;
$projectType = ($record['project_type'] ?? '') === 'OTHER'
    ? trim($record['project_other'] ?? '')
    : trim(($record['project_type_label'] ?? $record['project_type'] ?? '').' '.($record['project_other'] ?? ''));
$category = ($record['category'] ?? '') === 'OTHER'
    ? trim($record['category_other'] ?? '')
    : ($record['category_label'] ?? $record['category'] ?? '');
$coordinatorName = trim($text('coordinator_first').' '.$text('coordinator_last'));
$thaiDate = function ($date): string {
    if (blank($date)) {
        return '';
    }

    $parsed = \Carbon\Carbon::parse($date)->locale('th');

    return $parsed->translatedFormat('j F').' '.($parsed->year + 543);
};
$learningPeriod = trim($thaiDate($record['starts_at'] ?? null).' ถึง '.$thaiDate($record['ends_at'] ?? null));
$enrollment = ($record['enrollment'] ?? '') === 'อื่น ๆ (ระบุ)'
    ? trim($record['enrollment_other'] ?? '')
    : trim($record['enrollment'] ?? '');
$officerPassed = ($record['officer_decision'] ?? null) === 'PASSED';
$approved = ($record['approval_decision'] ?? null) === 'APPROVED';
$rejected = in_array($record['approval_decision'] ?? null, ['REJECTED', 'RETURNED'], true);
@endphp

<main class="print-document">
    <section class="document-sheet document-page-one">
        <table class="document-header" cellpadding="0" cellspacing="0">
            <tr>
                <td class="document-header__logo" width="65" valign="middle">
                    <img src="{{ $documentLogo ?? asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ">
                </td>
                <td class="document-header__title" valign="middle">
                    <h1>แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy</h1>
                </td>
            </tr>
        </table>

        <section class="document-section">
            <h2>ส่วนที่ 1 : ข้อมูลส่วนงานและผู้ประสานงานโครงการ</h2>
            <dl class="document-fields">
                <div class="document-field document-field--wide">
                    <dt>ส่วนงาน/คณะ/สำนักของผู้ยื่น</dt>
                    <dd>{{ $text('requester_unit') }}</dd>
                </div>
                <div class="document-field document-field--wide">
                    <dt>ส่วนงาน/คณะ/สำนักเป้าหมาย</dt>
                    <dd>{{ $text('unit') }}</dd>
                </div>
                <div class="document-field document-field--wide">
                    <dt>ชื่อโครงการ</dt>
                    <dd>{{ $text('project_name') }}</dd>
                </div>
                <div class="document-field document-field--wide">
                    <dt>ประเภทโครงการ</dt>
                    <dd>{{ $projectType }}</dd>
                </div>
                <div class="document-field document-field--left">
                    <dt>ชื่อ-นามสกุลผู้ประสานงาน</dt>
                    <dd>{{ $coordinatorName }}</dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>ตำแหน่ง</dt>
                    <dd>{{ $text('coordinator_position') }}</dd>
                </div>
                <div class="document-field document-field--left">
                    <dt>เบอร์โทรศัพท์ติดต่อ / ภายใน</dt>
                    <dd>{{ $text('coordinator_phone') }}</dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>E-mail</dt>
                    <dd>{{ $text('coordinator_email') }}</dd>
                </div>
            </dl>
        </section>

        <section class="document-section">
            <h2>ส่วนที่ 2 : รายละเอียดรายวิชาที่ต้องการสร้าง</h2>
            <dl class="document-fields">
                <div class="document-field document-field--wide">
                    <dt>ชื่อรายวิชา (ภาษาไทย)</dt>
                    <dd>{{ $text('course_th') }}</dd>
                </div>
                <div class="document-field document-field--wide">
                    <dt>Course Title (EN)</dt>
                    <dd>{{ $text('course_en') }}</dd>
                </div>
                <div class="document-field document-field--wide">
                    <dt>หมวดหมู่</dt>
                    <dd>{{ $category }}</dd>
                </div>
                <div class="document-field document-field--wide document-field--description">
                    <dt>คำอธิบายรายวิชาและวัตถุประสงค์โดยย่อ</dt>
                    <dd>{{ $text('description') }}</dd>
                </div>
                @forelse($record['instructors'] ?? [] as $instructor)
                <div class="document-field document-field--left">
                    <dt>ชื่อ-นามสกุลอาจารย์ผู้สอน {{ $loop->iteration }}</dt>
                    <dd>{{ trim($instructor['first'].' '.$instructor['last']) }}</dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>E-mail</dt>
                    <dd>{{ $instructor['email'] }}</dd>
                </div>
                @empty
                <div class="document-field document-field--left">
                    <dt>ชื่อ-นามสกุลอาจารย์ผู้สอน</dt>
                    <dd></dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>E-mail</dt>
                    <dd></dd>
                </div>
                @endforelse
            </dl>
        </section>

        <section class="document-section">
            <h2>ส่วนที่ 3 : ระยะเวลาเปิด-ปิด และลักษณะการดำเนินกิจกรรม</h2>
            <dl class="document-fields">
                <div class="document-field document-field--wide">
                    <dt>ลักษณะการดำเนินกิจกรรม</dt>
                    <dd>{{ $text('learning') }}</dd>
                </div>
                @if(($record['learning'] ?? '') === 'เปิดแบบตามวงรอบ (Phase/Batch-based)')
                <div class="document-field document-field--left">
                    <dt>วงรอบ/รุ่นที่</dt>
                    <dd>{{ $text('activity_round') }}</dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>เฟส</dt>
                    <dd>{{ $text('activity_phase') }}</dd>
                </div>
                @endif
                <div class="document-field document-field--wide">
                    <dt>ระยะเวลาดำเนินกิจกรรม</dt>
                    <dd>{{ $learningPeriod }}</dd>
                </div>
                <div class="document-field document-field--left">
                    <dt>รูปแบบการเข้ารายวิชา</dt>
                    <dd>{{ $enrollment }}</dd>
                </div>
                <div class="document-field document-field--right">
                    <dt>จำนวนผู้เรียนที่คาดการณ์</dt>
                    <dd>{{ $text('expected_students') }} คน</dd>
                </div>
            </dl>
        </section>
    </section>

    <section class="document-sheet document-page-two">
        <table class="document-header document-header--compact" cellpadding="0" cellspacing="0">
            <tr>
                <td class="document-header__logo" width="65" valign="middle">
                    <img src="{{ $documentLogo ?? asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ">
                </td>
                <td class="document-header__title" valign="middle">
                    <h1>แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy</h1>
                </td>
            </tr>
        </table>

        <section class="document-section">
            <h2>ส่วนที่ 4 : คำรับรองและการอนุมัติ</h2>
            <p class="document-certification">ข้าพเจ้าขอรับรองว่าข้อมูลและสื่อการสอนที่นำขึ้นสู่ระบบเป็นไปตาม พ.ร.บ. ว่าด้วยการกระทำความผิดเกี่ยวกับคอมพิวเตอร์ และไม่ละเมิดลิขสิทธิ์ ทรัพย์สินทางปัญญา หรือสิทธิของบุคคลอื่น พร้อมทั้งจะกำกับดูแล การจัดการเรียนการสอน ให้เป็นไปตามมาตรฐานมหาวิทยาลัย</p>
            <div class="document-signatures">
                <div class="document-signature">
                    <h3>ผู้ขอสร้างรายวิชา / ผู้ประสานงาน</h3>
                    <p>ลงชื่อ <span></span></p>
                    <p>( <span>{{ $coordinatorName }}</span> )</p>
                    <p>ตำแหน่ง <span>{{ $text('coordinator_position') }}</span></p>
                    <p>วันที่ <span>{{ now()->locale('th')->translatedFormat('j F Y') }}</span></p>
                </div>
                <div class="document-signature">
                    <h3>หัวหน้าส่วนงาน / คณบดี / ผู้อำนวยการ</h3>
                    <p>ลงชื่อ <span></span></p>
                    <p>( <span></span> )</p>
                    <p>ตำแหน่ง <span></span></p>
                    <p>วันที่ <span></span></p>
                </div>
            </div>
        </section>

        <section class="document-section document-section--staff">
            <h2>ส่วนที่ 5 : สำหรับเจ้าหน้าที่ผู้ดูแลระบบ</h2>
            <div class="document-staff-grid">
                <section class="document-staff-card">
                    <h3>1. เจ้าหน้าที่รับเอกสาร</h3>
                    <p><span class="document-checkbox {{ $officerPassed ? 'is-checked' : '' }}" aria-hidden="true"></span> ตรวจสอบเอกสารครบถ้วน</p>
                    <p>ลงชื่อ <span class="document-line">{{ $text('officer_name') }}</span></p>
                    <p>วันที่ <span class="document-line">{{ $text('officer_reviewed_at') }}</span></p>
                </section>
                <section class="document-staff-card">
                    <h3>2. ผู้มีอำนาจพิจารณา</h3>
                    <p class="document-decisions"><span><span class="document-checkbox {{ $approved ? 'is-checked' : '' }}" aria-hidden="true"></span> อนุมัติ</span><span><span class="document-checkbox {{ $rejected ? 'is-checked' : '' }}" aria-hidden="true"></span> ไม่อนุมัติ</span></p>
                    <p>ข้อคิดเห็น <span class="document-line">{{ $text('approval_comment') }}</span></p>
                    <p>ลงชื่อ <span class="document-line">{{ $text('approver_name') }}</span></p>
                    <p>วันที่ <span class="document-line">{{ $text('approval_decided_at') }}</span></p>
                </section>
            </div>
        </section>
    </section>
</main>
