<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 480px) {
            .email-card-content { padding: 20px !important; }
            .email-label,
            .email-value { display: block !important; width: auto !important; }
            .email-label { padding-bottom: 0 !important; border-bottom: 0 !important; }
            .email-value { padding-top: 4px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-family:'Sarabun','Noto Sans Thai',Tahoma,Arial,sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:800px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:#123365;color:#ffffff;font-size:20px;font-weight:700;">แบบฟอร์มขอรับบริการ สำนักคอมพิวเตอร์</td>
                    </tr>
                    <tr>
                        <td class="email-card-content" style="padding:28px;">
                            @if($heading)
                                <h1 style="margin:0 0 18px;font-size:22px;line-height:1.45;">{{ $heading }}</h1>
                            @endif
                            @if($greeting)
                                <p style="margin:0 0 16px;line-height:1.7;">เรียน {{ $greeting }}</p>
                            @endif
                            <p style="margin:0 0 24px;color:#374151;line-height:1.75;">{!! nl2br(e($intro)) !!}</p>

                            @if($resultText)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;background:{{ ($resultTone ?? 'info') === 'danger' ? '#fef2f2' : '#f0f7ff' }};border:1px solid {{ ($resultTone ?? 'info') === 'danger' ? '#fecaca' : '#bfdbfe' }};border-radius:6px;">
                                    <tr>
                                        <td align="{{ ($resultTone ?? 'info') === 'danger' ? 'center' : 'left' }}" style="padding:16px 18px;line-height:1.75;text-align:{{ ($resultTone ?? 'info') === 'danger' ? 'center' : 'left' }};overflow-wrap:anywhere;word-break:break-word;">
                                            <strong style="display:block;margin-bottom:6px;color:{{ ($resultTone ?? 'info') === 'danger' ? '#991b1b' : '#123365' }};">{{ $resultTitle ?? 'ผลการดำเนินการ' }}</strong>
                                            {!! nl2br(e($resultText)) !!}
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;border-collapse:collapse;">
                                @foreach($rows as $label => $value)
                                    <tr>
                                        <td class="email-label" style="padding:10px 12px 10px 0;color:#6b7280;width:190px;border-bottom:1px solid #e5e7eb;vertical-align:top;line-height:1.6;">{{ $label }}</td>
                                        <td class="email-value" style="padding:10px 0;font-weight:600;border-bottom:1px solid #e5e7eb;vertical-align:top;line-height:1.6;word-break:break-word;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            @if($actionUrl && $actionLabel)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;">
                                    <tr>
                                        <td style="border-radius:6px;background:#123365;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 22px;color:#ffffff;text-decoration:none;font-weight:700;line-height:1.4;">{{ $actionLabel }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if($expiresAtText)
                                <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;"><strong>ลิงก์หมดอายุวันที่ {{ $expiresAtText }}</strong><br>ลิงก์นี้ใช้สำหรับคำร้องนี้เท่านั้น</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
