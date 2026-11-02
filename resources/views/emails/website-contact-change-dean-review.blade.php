<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>คำขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์รอการรับรอง</title></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <tr><td style="padding:24px 28px;background:#123365;color:#fff;font-size:20px;font-weight:700;">แบบฟอร์มขอรับบริการ สำนักคอมพิวเตอร์</td></tr>
        <tr><td style="padding:28px;">
            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.4;">มีคำขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์รอการรับรอง</h1>
            <p style="margin:0 0 24px;color:#4b5563;line-height:1.7;">กรุณาตรวจสอบข้อมูลและเลือกผลการรับรอง ระบบจะส่งต่อให้เจ้าหน้าที่ดำเนินการโดยอัตโนมัติ</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;border-collapse:collapse;">
                @foreach([
                    'เลขที่คำร้อง' => $request->request_no,
                    'ส่วนงาน/คณะ/สำนัก' => $departmentName,
                    'หน่วยงานย่อย' => $subdepartmentName,
                    'ชื่อเว็บไซต์' => $website?->website_name ?: '—',
                    'โดเมน (URL)' => $website?->domain_url ?: '—',
                    'ผู้ดูแลเว็บไซต์เดิม' => $currentWebsiteContact?->full_name ?: '—',
                    'ผู้ดูแลเว็บไซต์คนใหม่' => $newWebsiteContact?->full_name ?: '—',
                ] as $label => $value)
                <tr><td style="padding:10px 0;color:#6b7280;width:180px;border-bottom:1px solid #e5e7eb;vertical-align:top;">{{ $label }}</td><td style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $value }}</td></tr>
                @endforeach
            </table>
            <p style="margin:0 0 24px;"><a href="{{ $detailsUrl }}" style="display:inline-block;padding:12px 22px;border-radius:6px;background:#123365;color:#fff;text-decoration:none;font-weight:700;">ดูรายละเอียดและพิจารณาคำร้อง</a></p>
            <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;"><strong>ลิงก์หมดอายุวันที่ {{ $expiresAtText }}</strong><br>ลิงก์นี้ใช้สำหรับคำร้องนี้เท่านั้น</p>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
