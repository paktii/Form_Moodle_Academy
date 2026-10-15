@props(['status', 'returnedBy' => null, 'decisionSource' => null])
@php
    $stage = match ($status) {
        'PENDING_SIGNED_DOCUMENT' => 0,
        'PENDING_DEAN_REVIEW' => 1,
        'UNDER_OFFICER_REVIEW' => 2,
        'RETURNED_FOR_REVISION' => $returnedBy === 'APPROVER' ? 3 : 2,
        'REJECTED' => $decisionSource === 'DEAN' ? 1 : 3,
        default => 3,
    };
    $finished = in_array($status, ['PENDING_COURSE_ID', 'COURSE_ID_RECORDED']);
    $rejected = in_array($status, ['RETURNED_FOR_REVISION', 'REJECTED']);
    $steps = [
        ['ยื่นคำร้องสำเร็จ', 'กรอกข้อมูลคำร้องเรียบร้อย', 'check'],
        ['รับรองโดยส่วนงาน', 'ผู้รับรองของส่วนงานเป้าหมายพิจารณาคำร้อง', 'clipboard-check'],
        ['ตรวจสอบเอกสาร', 'เจ้าหน้าที่ตรวจสอบคำขอ', 'file-text'],
        ['อนุมัติสร้างรายวิชา', 'ผู้มีอำนาจพิจารณาคำร้อง', 'file-check'],
    ];
@endphp
<ol class="request-progress" aria-label="ขั้นตอนดำเนินการคำร้อง">
    @foreach($steps as $index => [$title, $description, $icon])
        @php($state = $finished || $index < $stage ? 'complete' : ($index === $stage ? ($rejected ? 'returned' : 'current') : 'pending'))
        <li class="request-progress__step request-progress__step--{{ $state }}" @if($index === $stage) aria-current="step" @endif>
            <span class="request-progress__icon"><x-portal-icon :name="$state === 'complete' ? 'check' : $icon" /></span>
            <strong>{{ $title }}</strong><span>{{ $description }}</span>
        </li>
    @endforeach
</ol>
