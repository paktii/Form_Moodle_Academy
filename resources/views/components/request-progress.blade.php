@props(['status'])
@php
    $stage = match ($status) {
        'DRAFT', 'PENDING_SIGNED_DOCUMENT' => 0,
        'UNDER_OFFICER_REVIEW', 'RETURNED_FOR_REVISION' => 1,
        default => 2,
    };
    $finished = in_array($status, ['PENDING_COURSE_ID', 'COURSE_ID_RECORDED']);
    $rejected = in_array($status, ['RETURNED_FOR_REVISION', 'REJECTED']);
    $steps = [
        ['ยื่นคำร้องสำเร็จ', 'กรอกข้อมูลและอัปโหลดเอกสารเรียบร้อย', 'check'],
        ['ตรวจสอบเอกสาร', 'เจ้าหน้าที่ตรวจสอบคำขอ', 'file-text'],
        ['อนุมัติสร้างรายวิชา', 'ผู้มีอำนาจพิจารณาคำร้อง', 'file-code'],
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
