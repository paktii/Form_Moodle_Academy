@props(['status', 'decisionSource' => null, 'deanApprovedAt' => null])
@php
    $stage = match ($status) {
        'PENDING_DEAN_REVIEW' => 1,
        'UNDER_OFFICER_REVIEW' => 2,
        'NOT_ENDORSED' => 1,
        'REJECTED' => $decisionSource === 'DEAN' ? 1 : 3,
        default => 3,
    };
    $finished = in_array($status, ['COURSE_ID_RECORDED', 'COMPLETED']);
    $rejected = in_array($status, ['REJECTED', 'NOT_ENDORSED'], true);
    $deanStepDescription = $status === 'NOT_ENDORSED'
        ? 'ผู้รับรองของส่วนงานไม่รับรองคำร้อง'
        : ($deanApprovedAt ? 'อนุมัติเมื่อ '.$deanApprovedAt : 'ผู้รับรองของส่วนงานเป้าหมายพิจารณาคำร้อง');
    $steps = [
        ['ยื่นคำร้องสำเร็จ', 'กรอกข้อมูลคำร้องเรียบร้อย', 'check'],
        ['รับรองโดยส่วนงาน', $deanStepDescription, 'clipboard-check'],
        ['ตรวจสอบเอกสาร', 'เจ้าหน้าที่ตรวจสอบคำขอ', 'file-text'],
        ['ดำเนินการเสร็จสิ้น', 'เจ้าหน้าที่อนุมัติและบันทึกรายละเอียด', 'file-check'],
    ];
@endphp
<ol class="request-progress" aria-label="ขั้นตอนดำเนินการคำร้อง">
    @foreach($steps as $index => [$title, $description, $icon])
        @php($state = $finished || $index < $stage ? 'complete' : ($index === $stage ? ($rejected ? 'rejected' : 'current') : 'pending'))
        <li class="request-progress__step request-progress__step--{{ $state }}" @if($index === $stage) aria-current="step" @endif>
            <span class="request-progress__icon"><x-portal-icon :name="$state === 'complete' ? 'check' : $icon" /></span>
            <strong>{{ $title }}</strong><span>{{ $description }}</span>
        </li>
    @endforeach
</ol>
