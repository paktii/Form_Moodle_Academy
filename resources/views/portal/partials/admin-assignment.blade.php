@php
    $isHead = $kind === 'head';
    $assignmentId = $isHead ? $assignment->head_assignment_id : $assignment->officer_assignment_id;
    $personId = $isHead ? $assignment->head_pers_id : $assignment->officer_pers_id;
    $role = $isHead ? 'หัวหน้าผู้รับผิดชอบ' : 'เจ้าหน้าที่ผู้รับผิดชอบ';
    $removeRoute = $isHead
        ? route('admin.form-heads.remove', $assignmentId)
        : route('admin.form-officers.remove', $assignmentId);
@endphp
<div class="admin-assignment" data-admin-assignment-id="{{ $assignmentId }}">
    <span>
        <strong>{{ $person['name'] ?? 'บุคลากร #'.$personId }}</strong>
        <small>{{ $person['buasri_id'] ?? '' }}{{ filled($person['email'] ?? '') ? ' · '.$person['email'] : '' }}</small>
    </span>
    <form method="post" action="{{ $removeRoute }}" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="{{ $kind }}" data-confirm-mode="remove" data-confirm-person="{{ $person['name'] ?? 'บุคลากร #'.$personId }}" data-confirm-role="{{ $role }}" data-confirm-form="{{ $formType }}">
        @csrf
        @method('delete')
        <x-portal-button type="submit" variant="danger-outline" aria-label="ยกเลิกสิทธิ์{{ $role }}ของ {{ $person['name'] ?? 'บุคลากร #'.$personId }}" title="ยกเลิกสิทธิ์"><x-portal-icon name="x" /></x-portal-button>
    </form>
</div>
