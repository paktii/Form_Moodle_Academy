@props(['status'])
@php
    $labels = config('course-workflow.statuses');
    $label = $labels[$status] ?? $status;
    $variant = match ($status) {
        'PENDING_DEAN_REVIEW', 'UNDER_OFFICER_REVIEW', 'PENDING_APPROVAL', 'รอตรวจสอบ', 'รออนุมัติ' => 'waiting',
        'COURSE_ID_RECORDED', 'COMPLETED', 'เสร็จสมบูรณ์' => 'complete',
        'REJECTED', 'NOT_ENDORSED', 'ไม่อนุมัติ', 'ไม่รับรอง' => 'rejected',
        default => 'neutral',
    };
@endphp
<span class="status-badge status-badge--{{ $variant }}">{{ $label }}</span>
