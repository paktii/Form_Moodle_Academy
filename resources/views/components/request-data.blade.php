@props(['record', 'variant' => 'review'])
@php
    $projectType = $record['project_type_label'] ?? ($record['project_type'] ?? '');
    $category = $record['category_label'] ?? ($record['category'] ?? '');
    $learningPeriod = ($record['learning'] ?? '') === 'แบบเรียนรู้ตามอัธยาศัยตลอดเวลา'
        ? 'ไม่มีช่วงเวลาเปิด-ปิด'
        : (($record['starts_at'] ?? '—').' ถึง '.($record['ends_at'] ?? '—'));
    $groups = [
        'ข้อมูลส่วนงานและโครงการ' => [
            ['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '', true],
            ['ส่วนงาน/คณะ/สำนักเป้าหมาย', $record['unit'] ?? '', true],
            ['ชื่อโครงการ', $record['project_name'] ?? '', true],
            ['ประเภทโครงการ', trim($projectType.' '.($record['project_other'] ?? '')), true],
            ['ชื่อผู้ประสานงาน', trim(($record['coordinator_first'] ?? '').' '.($record['coordinator_last'] ?? ''))],
            ['ตำแหน่ง', $record['coordinator_position'] ?? ''],
            ['เบอร์โทรศัพท์ติดต่อ / ภายใน', $record['coordinator_phone'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['coordinator_email'] ?? ''],
        ],
        'รายละเอียดรายวิชา' => [
            ['ชื่อรายวิชา (ภาษาไทย)', $record['course_th'] ?? ''], ['Course Title (English)', $record['course_en'] ?? ''],
            ['รหัสวิชาในระบบ', $record['subject_code'] ?? ''], ['หมวดหมู่', $category],
            ['คำอธิบายรายวิชาโดยย่อ', $record['description'] ?? ''],
        ],
    ];
@endphp
@foreach($groups as $heading => $fields)
    <section class="detail-section"><h3>{{ $variant === 'summary' && $loop->first ? 'ข้อมูลพื้นฐานเอกสาร' : $heading }}</h3><dl class="detail-grid">
        @foreach($fields as $field)<div class="detail-field {{ ($field[2] ?? false) ? 'detail-field--wide' : '' }}"><dt>{{ $field[0] }}</dt><dd>{{ $field[1] ?: '—' }}</dd></div>@endforeach
        @if($heading === 'รายละเอียดรายวิชา')
            @foreach($record['instructors'] ?? [] as $instructor)<div class="detail-field"><dt>อาจารย์ผู้สอนหลัก {{ $loop->iteration }}</dt><dd>{{ $instructor['first'] }} {{ $instructor['last'] }}</dd></div><div class="detail-field"><dt>Email</dt><dd>{{ $instructor['email'] }}</dd></div>@endforeach
        @endif
    </dl></section>
@endforeach
<section class="detail-section"><h3>ระยะเวลาเปิด-ปิด และรูปแบบการเรียนการสอน</h3><dl class="detail-grid detail-grid--four">
    <div class="detail-field"><dt>ระยะเวลาเปิด-ปิดรายวิชา:</dt><dd>{{ $learningPeriod }}</dd></div>
    <div class="detail-field"><dt>รูปแบบการเปิดสอน:</dt><dd>{{ $record['learning'] ?? '—' }}</dd></div>
    <div class="detail-field"><dt>รูปแบบการเข้ารายวิชา:</dt><dd>{{ $record['enrollment'] ?? '—' }} {{ $record['enrollment_other'] ?? '' }}</dd></div>
    <div class="detail-field"><dt>จำนวนผู้เรียนที่คาดการณ์:</dt><dd>{{ $record['expected_students'] ?? '—' }}</dd></div>
</dl></section>
