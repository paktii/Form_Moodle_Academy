<?php

return [
    'steps' => [
        1 => 'ข้อมูลส่วนงานและโครงการ',
        2 => 'รายละเอียดรายวิชาที่ต้องการสร้างในระบบ',
        3 => 'ระยะเวลาเปิด-ปิด และรูปแบบการเรียนการสอน',
        4 => 'ดาวน์โหลดเอกสารเพื่อลงนาม',
    ],

    'statuses' => [
        'DRAFT' => 'แบบร่าง',
        'PENDING_SIGNED_DOCUMENT' => 'เอกสารไม่ครบ',
        'UNDER_OFFICER_REVIEW' => 'รอตรวจสอบ',
        'RETURNED_FOR_REVISION' => 'ส่งกลับแก้ไข',
        'PENDING_APPROVAL' => 'รออนุมัติ',
        'REJECTED' => 'ไม่อนุมัติ',
        'PENDING_COURSE_ID' => 'อนุมัติแล้ว',
        'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
    ],

    // Temporary identities until Server 199 / university SSO is connected.
    'actors' => [
        'user' => [
            'pers_id' => (int) env('COURSE133_USER_PERS_ID', 100010),
            'dept_id' => (int) env('COURSE133_USER_DEPT_ID', 200),
            'buasri_id' => env('COURSE133_USER_BUASRI_ID', 'requester'),
            'password' => env('COURSE133_USER_PASSWORD'),
            'name' => env('COURSE133_USER_NAME', 'ผู้ยื่นคำร้อง'),
            'email' => env('COURSE133_USER_EMAIL', 'requester@example.test'),
            'label' => 'บุคลากร',
        ],
        'officer' => [
            'pers_id' => (int) env('COURSE133_OFFICER_PERS_ID', 100015),
            'buasri_id' => env('COURSE133_OFFICER_BUASRI_ID', 'officer'),
            'password' => env('COURSE133_OFFICER_PASSWORD'),
            'name' => env('COURSE133_OFFICER_NAME', 'เจ้าหน้าที่ตรวจสอบ'),
            'email' => env('COURSE133_OFFICER_EMAIL', 'officer@example.test'),
            'label' => 'เจ้าหน้าที่',
        ],
        'approver' => [
            'pers_id' => (int) env('COURSE133_APPROVER_PERS_ID', 100017),
            'buasri_id' => env('COURSE133_APPROVER_BUASRI_ID', 'approver'),
            'password' => env('COURSE133_APPROVER_PASSWORD'),
            'name' => env('COURSE133_APPROVER_NAME', 'ผู้มีอำนาจอนุมัติ'),
            'email' => env('COURSE133_APPROVER_EMAIL', 'approver@example.test'),
            'label' => 'ผู้อนุมัติ',
        ],
    ],

];
