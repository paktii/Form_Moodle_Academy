@props(['record', 'variant' => 'review', 'showRequesterUnit' => true, 'splitTargetUnit' => false])
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
    $targetUnit = collect([$record['unit'] ?? null, $record['subunit'] ?? null])
        ->filter(fn ($value) => filled($value))
        ->implode(', ');
    $formTypeCode = $record['form_type_code'] ?? 'CC-ITED-06';
    $isWebsiteRequest = $formTypeCode === 'CC-ITED-01';
    $isWebsiteContactChangeRequest = $formTypeCode === 'CC-ITED-02';
    $isWebsiteStorageRequest = $formTypeCode === 'CC-ITED-04';
    $isEmeetingRequest = $formTypeCode === 'CC-ITED-05';
    $websitePeriod = ($record['site_scope_type'] ?? null) === 'DEPARTMENT'
        ? 'ไม่มีกำหนด'
        : $thaiDate($record['service_starts_on'] ?? null).' ถึง '.$thaiDate($record['service_ends_on'] ?? null);
    $platform = $record['platform_label'] ?? $record['platform_code'] ?? '';
    $groups = $isWebsiteRequest ? [
        'ข้อมูลส่วนงานและเว็บไซต์' => [
            ...($showRequesterUnit ? [['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '']] : []),
            ...($splitTargetUnit
                ? [
                    ['ส่วนงาน/คณะ/สำนัก', $record['unit'] ?? ''],
                    ['หน่วยงานย่อย', $record['subunit'] ?? ''],
                ]
                : [['ส่วนงาน/คณะ/สำนัก', $targetUnit]]),
            ['ชื่อเว็บไซต์', $record['website_name'] ?? ''],
            ['ประเภทการขอใช้พื้นที่', $record['site_scope_label'] ?? ''],
            ['ระยะเวลาใช้งาน', $websitePeriod],
            ['โดเมน (URL) ที่ต้องการ', $record['domain_url'] ?? ''],
            ['รายละเอียดเว็บไซต์', $platform],
            ['รายละเอียดเพิ่มเติม', $record['additional_detail'] ?? '', true, true],
        ],
        'ข้อมูลผู้ดูแลเว็บไซต์' => [
            ['ชื่อ-นามสกุล', trim(($record['contact_first'] ?? '').' '.($record['contact_last'] ?? ''))],
            ['ตำแหน่ง', $record['contact_position'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['contact_email'] ?? ''],
            ['โทรศัพท์ติดต่อ / ภายใน', $record['contact_phone'] ?? ''],
        ],
    ] : ($isWebsiteContactChangeRequest ? [
        'ข้อมูลส่วนงานและเว็บไซต์' => [
            ...($showRequesterUnit ? [['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '']] : []),
            ...($splitTargetUnit
                ? [['ส่วนงาน/คณะ/สำนัก', $record['unit'] ?? ''], ['หน่วยงานย่อย', $record['subunit'] ?? '']]
                : [['ส่วนงาน/คณะ/สำนัก', $targetUnit]]),
            ['ชื่อเว็บไซต์', $record['website_name'] ?? ''],
            ['โดเมน (URL)', $record['domain_url'] ?? ''],
        ],
        'ข้อมูลผู้ดูแลเว็บไซต์เดิม' => [
            ['ชื่อ-นามสกุล', trim(($record['current_contact_first'] ?? '').' '.($record['current_contact_last'] ?? ''))],
        ],
        'ข้อมูลผู้ดูแลเว็บไซต์คนใหม่' => [
            ['ชื่อ-นามสกุล', trim(($record['new_contact_first'] ?? '').' '.($record['new_contact_last'] ?? ''))],
            ['ตำแหน่ง', $record['new_contact_position'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['new_contact_email'] ?? ''],
            ['โทรศัพท์ติดต่อ / ภายใน', $record['new_contact_phone'] ?? ''],
        ],
    ] : ($isWebsiteStorageRequest ? [
        'ข้อมูลส่วนงานและเว็บไซต์' => [
            ...($showRequesterUnit ? [['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '']] : []),
            ...($splitTargetUnit
                ? [['ส่วนงาน/คณะ/สำนัก', $record['unit'] ?? ''], ['หน่วยงานย่อย', $record['subunit'] ?? '']]
                : [['ส่วนงาน/คณะ/สำนัก', $targetUnit]]),
            ['ชื่อเว็บไซต์', $record['website_name'] ?? ''],
            ['โดเมน (URL)', $record['domain_url'] ?? ''],
            ['พื้นที่ที่ขอเพิ่ม', rtrim(rtrim((string) ($record['requested_space_gb'] ?? '1'), '0'), '.').' GB'],
        ],
        'ข้อมูลผู้ดูแลเว็บไซต์' => [
            ['ชื่อ-นามสกุล', trim(($record['contact_first'] ?? '').' '.($record['contact_last'] ?? ''))],
            ['ตำแหน่ง', $record['contact_position'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['contact_email'] ?? ''],
            ['โทรศัพท์ติดต่อ / ภายใน', $record['contact_phone'] ?? ''],
        ],
    ] : ($isEmeetingRequest ? [
        'ข้อมูลส่วนงานและประเภทการขอรับบริการ' => [
            ...($showRequesterUnit ? [['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '']] : []),
            ...($splitTargetUnit
                ? [['ส่วนงาน/คณะ/สำนัก', $record['unit'] ?? ''], ['หน่วยงานย่อย', $record['subunit'] ?? '']]
                : [['ส่วนงาน/คณะ/สำนัก', $targetUnit]]),
            ['ขอรับบริการ', ($record['action_type'] ?? '') === 'CHANGE_CONTACT' ? 'เปลี่ยนแปลงสิทธิ์ผู้ดูแล' : 'ขอสิทธิ์ผู้ดูแล'],
        ],
        ...(($record['action_type'] ?? '') === 'CHANGE_CONTACT' ? [
            'ข้อมูลผู้ดูแลกรอบการประชุมเดิม' => [
                ['ชื่อ-นามสกุล', trim(($record['current_contact_first'] ?? '').' '.($record['current_contact_last'] ?? ''))],
            ],
        ] : []),
        'ข้อมูลผู้ดูแลกรอบการประชุมที่จะได้รับสิทธิ์' => [
            ['ชื่อ-นามสกุล', trim(($record['contact_first'] ?? '').' '.($record['contact_last'] ?? ''))],
            ['ตำแหน่ง', $record['contact_position'] ?? ''],
            ['E-mail (มหาวิทยาลัย)', $record['contact_email'] ?? ''],
            ['โทรศัพท์ติดต่อ / ภายใน', $record['contact_phone'] ?? ''],
        ],
    ] : [
        'ข้อมูลส่วนงานและโครงการ' => [
            ...($showRequesterUnit ? [['ส่วนงาน/คณะ/สำนักของผู้ยื่น', $record['requester_unit'] ?? '']] : []),
            ...($splitTargetUnit
                ? [
                    ['ส่วนงาน/คณะ/สำนักเป้าหมาย', $record['unit'] ?? ''],
                    ['ภาควิชา/ส่วนงานย่อยเป้าหมาย', $record['subunit'] ?? ''],
                ]
                : [['ส่วนงาน/คณะ/สำนักเป้าหมาย', $targetUnit]]),
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
    ])));
@endphp
@foreach($groups as $heading => $fields)
    <section class="detail-section"><h3>{{ $variant === 'summary' && $loop->first ? 'ข้อมูลพื้นฐานเอกสาร' : $heading }}</h3><dl class="detail-grid">
        @foreach($fields as $field)<div class="detail-field {{ ($field[2] ?? false) ? 'detail-field--wide' : '' }}"><dt>{{ $field[0] }}</dt><dd @class(['detail-field__multiline' => ($field[3] ?? false)])>{{ $field[1] ?: '—' }}</dd></div>@endforeach
        @if(! $isWebsiteRequest && ! $isWebsiteContactChangeRequest && ! $isWebsiteStorageRequest && ! $isEmeetingRequest && $heading === 'รายละเอียดรายวิชา')
            @foreach($record['instructors'] ?? [] as $instructor)<div class="detail-field"><dt>อาจารย์ผู้สอน {{ $loop->iteration }}</dt><dd>{{ $instructor['first'] }} {{ $instructor['last'] }}</dd></div><div class="detail-field"><dt>Email</dt><dd>{{ $instructor['email'] }}</dd></div>@endforeach
        @endif
    </dl></section>
@endforeach
@if(! $isWebsiteRequest && ! $isWebsiteContactChangeRequest && ! $isWebsiteStorageRequest && ! $isEmeetingRequest)
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
@endif
