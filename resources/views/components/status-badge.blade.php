@props(['status'])
@php
    $labels = config('course-workflow.statuses');
    $label = $labels[$status] ?? $status;
    $variant = match ($status) {
        'UNDER_OFFICER_REVIEW', 'PENDING_APPROVAL', 'รอตรวจสอบ', 'รออนุมัติ' => 'waiting',
        'PENDING_COURSE_ID', 'อนุมัติแล้ว' => 'approved',
        'COURSE_ID_RECORDED', 'เสร็จสมบูรณ์' => 'complete',
        'REJECTED', 'RETURNED_FOR_REVISION', 'ไม่อนุมัติ' => 'rejected',
        'PENDING_SIGNED_DOCUMENT' => 'incomplete',
        default => 'neutral',
    };
@endphp
<span class="status-badge status-badge--{{ $variant }}">{{ $label }}</span>
