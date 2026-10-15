<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>คำร้องรอคณบดีรับรอง</title>
</head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr><td style="padding:24px 28px;background:#123365;color:#ffffff;font-size:20px;font-weight:700;">SWU Moodle Academy</td></tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.4;">มีคำร้องขอสร้างรายวิชาในระบบ SWU Moodle Academy รอคณบดีรับรอง</h1>
                            <p style="margin:0 0 24px;color:#4b5563;line-height:1.7;">กรุณาเปิดหน้ารายละเอียดเพื่อตรวจสอบข้อมูลและเลือกผลการรับรอง</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;border-collapse:collapse;">
                                <tr><td style="padding:10px 0;color:#6b7280;width:180px;border-bottom:1px solid #e5e7eb;vertical-align:top;">เลขที่คำร้อง</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $request->request_no }}</td></tr>
                                <tr><td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;vertical-align:top;">ส่วนงาน/คณะ/สำนัก</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $departmentName }}</td></tr>
                                <tr><td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;vertical-align:top;">ภาควิชา/ส่วนงานย่อย</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $subdepartmentName }}</td></tr>
                                <tr><td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;">ชื่อโครงการ</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $request->project_name }}</td></tr>
                                <tr><td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;">ชื่อรายวิชา</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $request->course_name_th }}</td></tr>
                                <tr><td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;vertical-align:top;">ระยะเวลาดำเนินกิจกรรม</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $activityPeriod }}</td></tr>
                                <tr>
                                    <td style="padding:10px 0;color:#6b7280;border-bottom:1px solid #e5e7eb;vertical-align:top;">รายชื่ออาจารย์ผู้สอน</td>
                                    <td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">
                                        @forelse($instructorNames as $instructorName)
                                            <div>{{ $loop->iteration }}. {{ $instructorName }}</div>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                </tr>
                                <tr><td style="padding:10px 0;color:#6b7280;vertical-align:top;">จำนวนผู้เรียนที่คาดการณ์</td><td style="padding:10px 0;font-weight:600;">{{ $request->expected_students }} คน</td></tr>
                            </table>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;">
                                <tr>
                                    <td><a href="{{ $detailsUrl }}" style="display:inline-block;padding:12px 22px;border-radius:6px;background:#123365;color:#ffffff;text-decoration:none;font-weight:700;font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;">ดูรายละเอียดและพิจารณาคำร้อง</a></td>
                                </tr>
                            </table>
                            <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;"><strong>ลิงก์หมดอายุวันที่ {{ $expiresAtText }}</strong><br>ลิงก์นี้ใช้สำหรับคำร้องนี้เท่านั้น</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
