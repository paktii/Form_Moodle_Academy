@props(['record', 'variant' => 'review'])
@php
    $projectType = ($record['project_type'] ?? '') === 'OTHER'
        ? trim($record['project_other'] ?? '')
        : trim(($record['project_type_label'] ?? ($record['project_type'] ?? '')).' '.($record['project_other'] ?? ''));
    $category = ($record['category'] ?? '') === 'OTHER'
        ? trim($record['category_other'] ?? '')
        : ($record['category_label'] ?? ($record['category'] ?? ''));
    $thaiDate = function ($date): string {
        if (blank($date)) {
            return '—';
        }

        $parsed = \Carbon\Carbon::parse($date)->locale('th');

        return $parsed->translatedFormat('j F').' '.($parsed->year + 543);
    };
    $learningPeriod = $thaiDate($record['starts_at'] ?? null).' ถึง '.$thaiDate($record['ends_at'] ?? null);
    $enrollment = ($record['enrollment'] ?? '') === 'อื่น ๆ (ระบุ)'
        ? trim($record['enrollment_other'] ?? '')
        : trim($record['enrollment'] ?? '');
    $groups = [
        'ข้อมูลส่วนงานและโครงการ' => [
            ['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? ''],
            ['ส่วนงาน/คณะ/สำนักเป้าหมาย', $record['unit'] ?? ''],
            ['ชื่อโครงการ', $record['project_name'] ?? ''],
            ['ประเภทโครงการ', $projectType],
            ['ชื่อผู้ประสานงาน', trim(($record['coordinator_first'] ?? '').' '.($record['coordinator_last'] ?? ''))],
            ['ตำแหน่ง', $record['coordinator_position'] ?? ''],
            ['เบอร์โทรศัพท์ติดต่อ / ภายใน', $record['coordinator_phone'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['coordinator_email'] ?? ''],
        ],
        'รายละเอียดรายวิชา' => [
            ['ชื่อรายวิชา (ภาษาไทย)', $record['course_th'] ?? ''], ['Course Title (English)', $record['course_en'] ?? ''],
            ['หมวดหมู่', $category, true],
            ['คำอธิบายรายวิชาโดยย่อ', $record['description'] ?? '', true],
        ],
    ];
@endphp
@foreach($groups as $heading => $fields)
    <section class="detail-section"><h3>{{ $variant === 'summary' && $loop->first ? 'ข้อมูลพื้นฐานเอกสาร' : $heading }}</h3><dl class="detail-grid">
        @foreach($fields as $field)<div class="detail-field {{ ($field[2] ?? false) ? 'detail-field--wide' : '' }}"><dt>{{ $field[0] }}</dt><dd>{{ $field[1] ?: '—' }}</dd></div>@endforeach
        @if($heading === 'รายละเอียดรายวิชา')
            @foreach($record['instructors'] ?? [] as $instructor)<div class="detail-field"><dt>อาจารย์ผู้สอน {{ $loop->iteration }}</dt><dd>{{ $instructor['first'] }} {{ $instructor['last'] }}</dd></div><div class="detail-field"><dt>Email</dt><dd>{{ $instructor['email'] }}</dd></div>@endforeach
        @endif
    </dl></section>
@endforeach
<section class="detail-section"><h3>ระยะเวลาเปิด-ปิด <br class="requester-detail__mobile-heading-break">และลักษณะการดำเนินกิจกรรม</h3><dl class="detail-grid">
    <div class="detail-field"><dt>ระยะเวลาดำเนินกิจกรรม:</dt><dd>{{ $learningPeriod }}</dd></div>
    <div class="detail-field"><dt>ลักษณะการดำเนินกิจกรรม:</dt><dd>{{ $record['learning'] ?? '—' }}</dd></div>
    @if(($record['learning'] ?? '') === 'เปิดแบบตามวงรอบ (Phase/Batch-based)')
    <div class="detail-field"><dt>วงรอบ/รุ่นที่:</dt><dd>{{ $record['activity_round'] ?? '—' }}</dd></div>
    <div class="detail-field"><dt>เฟส:</dt><dd>{{ $record['activity_phase'] ?? '—' }}</dd></div>
    @endif
    <div class="detail-field"><dt>รูปแบบการเข้ารายวิชา:</dt><dd>{{ $enrollment ?: '—' }}</dd></div>
    <div class="detail-field"><dt>จำนวนผู้เรียนที่คาดการณ์:</dt><dd>{{ $record['expected_students'] ?? '—' }}</dd></div>
</dl></section>
