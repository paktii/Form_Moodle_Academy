<?php

return [
    'moodle_url' => env('COURSE133_MOODLE_URL', 'https://academy.swu.ac.th'),

    'dev_role_switcher' => (bool) env('PORTAL_DEV_ROLE_SWITCHER', false),

    'notifications' => [
        'schedule_lock_minutes' => max(1, (int) env('COURSE133_NOTIFICATION_LOCK_MINUTES', 5)),
        'stale_after_minutes' => max(1, (int) env('COURSE133_NOTIFICATION_STALE_MINUTES', 5)),
        'retry_delay_seconds' => max(1, (int) env('COURSE133_NOTIFICATION_RETRY_SECONDS', 30)),
        'max_retry_delay_seconds' => max(1, (int) env('COURSE133_NOTIFICATION_MAX_RETRY_SECONDS', 900)),
    ],

    'ldap' => [
        'url' => env('LDAP_URL', 'ldap://ldap.swu.ac.th'),
        'port' => (int) env('LDAP_PORT', 389),
        'user_dn' => env('LDAP_USER_DN', 'uid=%s,dc=swu,dc=ac,dc=th'),
        'timeout' => (int) env('LDAP_TIMEOUT', 5),
    ],

    'steps' => [
        1 => 'ข้อมูลส่วนงานและโครงการ',
        2 => 'รายละเอียดรายวิชาที่ต้องการสร้างในระบบ',
        3 => 'ระยะเวลาเปิด-ปิด และลักษณะการดำเนินกิจกรรม',
        4 => 'ดาวน์โหลดเอกสารเพื่อลงนาม',
    ],

    'statuses' => [

        'PENDING_SIGNED_DOCUMENT' => 'เอกสารไม่ครบ',
        'UNDER_OFFICER_REVIEW' => 'รออนุมัติ',
        'RETURNED_FOR_REVISION' => 'ส่งกลับแก้ไข',
        'PENDING_APPROVAL' => 'รออนุมัติ',
        'REJECTED' => 'ไม่อนุมัติ',
        'PENDING_COURSE_ID' => 'อนุมัติแล้ว',
        'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
    ],

];
