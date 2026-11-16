<tr data-admin-dean-row data-admin-department-id="{{ $assignment->target_dept_id }}">
    <td data-label="ส่วนงาน">{{ $departmentName }}</td>
    <td data-label="ผู้รับรองปัจจุบัน">{{ $person['name'] ?? 'บุคลากร #'.$assignment->dean_pers_id }}</td>
    <td data-label="Buasri ID">{{ $person['buasri_id'] ?? '-' }}</td>
    <td data-label="ตำแหน่ง">{{ $assignment->position_name ?: '-' }}</td>
</tr>
