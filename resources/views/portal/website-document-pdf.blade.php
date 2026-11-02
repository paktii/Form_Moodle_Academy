@php
    $regularFont = 'data:font/truetype;base64,'.base64_encode(file_get_contents(resource_path('fonts/Sarabun-Regular.ttf')));
    $boldFont = 'data:font/truetype;base64,'.base64_encode(file_get_contents(resource_path('fonts/Sarabun-Bold.ttf')));
    $pdfCss = file_get_contents(resource_path('css/pages/website-document-pdf.css'));
    $pdfCss = str_replace(
        ['../../fonts/Sarabun-Regular.ttf', '../../fonts/Sarabun-Bold.ttf'],
        [$regularFont, $boldFont],
        $pdfCss
    );
    $documentLogo = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('img/logo-pdf.jpg')));
    $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
    $requestCode = \Illuminate\Support\Str::afterLast($requestNumber, '-');
    $pdfTitle = $requestCode !== '' ? "CC-ITED-01_{$requestCode}.pdf" : 'CC-ITED-01.pdf';
    $text = fn (string $key, string $fallback = '') => filled($record[$key] ?? null) ? $record[$key] : $fallback;
    $thaiDate = function ($date): string {
        if (blank($date)) return '';
        $parsed = \Carbon\Carbon::parse($date)->locale('th');
        return $parsed->translatedFormat('j F').' '.($parsed->year + 543);
    };
    $scopeLabel = $record['site_scope_label'] ?? match ($record['site_scope_type'] ?? null) {
        'DEPARTMENT' => 'ส่วนงาน',
        'PROJECT' => 'โครงการ',
        'CONFERENCE' => 'ประชุมวิชาการ',
        default => '',
    };
    $platform = $text('platform_label', $text('platform_code'));
    $contactName = trim($text('contact_first').' '.$text('contact_last'));
    $deanApproved = ($record['dean_decision'] ?? null) === 'APPROVED';
    $officerPassed = ($record['officer_decision'] ?? null) === 'APPROVED';
    $officerRejected = ($record['officer_decision'] ?? null) === 'REJECTED';
    $officerReviewed = $officerPassed || $officerRejected;
@endphp
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $pdfTitle }}</title>
    <style>{!! $pdfCss !!}</style>
</head>
<body>
<main class="website-document">
    <table class="website-document__header" cellpadding="0" cellspacing="0">
        <tr>
            <td class="website-document__logo" width="65" valign="middle">
                <img src="{{ $documentLogo }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ">
            </td>
            <td class="website-document__heading" valign="middle">
                <h1>บริการขอใช้พื้นที่สำหรับจัดทำเว็บไซต์ สำหรับส่วนงาน/โครงการ/ประชุมวิชาการ</h1>
            </td>
        </tr>
    </table>

    <section class="website-fields">
        <div class="website-field"><span>ชื่อส่วนงาน</span><b>{{ collect([$record['unit'] ?? null, $record['subunit'] ?? null])->filter()->implode(' / ') }}</b></div>
        <div class="website-field"><span>ชื่อเว็บไซต์</span><b>{{ $text('website_name') }}</b></div>
        <div class="website-field"><span>ประเภทการขอใช้พื้นที่</span><b>{{ $scopeLabel }}</b></div>
        @if(($record['site_scope_type'] ?? null) !== 'DEPARTMENT')
        <div class="website-field website-field--split"><span>ระยะเวลา</span><span>เริ่มต้น</span><b>{{ $thaiDate($record['service_starts_on'] ?? null) }}</b><span>สิ้นสุด</span><b>{{ $thaiDate($record['service_ends_on'] ?? null) }}</b></div>
        @endif
        <div class="website-field"><span>โดเมน (URL) ที่ต้องการ</span><b>{{ $text('domain_url', '—') }}</b></div>
        <div class="website-field"><span>รายละเอียดเว็บไซต์</span><b>{{ $platform }}</b></div>
        <div class="website-field website-field--multiline"><span>รายละเอียดเพิ่มเติม</span><b>{{ $text('additional_detail', '—') }}</b></div>
        <div class="website-field"><span>ชื่อผู้ดูแลเว็บไซต์</span><b>{{ $contactName }}</b></div>
        <div class="website-field"><span>E-mail</span><b>{{ $text('contact_email') }}</b></div>
        <div class="website-field"><span>โทรศัพท์ติดต่อ / ภายใน</span><b>{{ $text('contact_phone') }}</b></div>
    </section>

    <section class="website-certification-box">
        <h2>คำรับรอง</h2>
        <p>ข้าพเจ้าขอรับรองว่าจะปฏิบัติตามกฏระเบียบ คำแนะนำ และประกาศต่าง ๆ เกี่ยวกับการใช้บริการเครือข่ายบัวศรี ซึ่งสำนักคอมพิวเตอร์กำหนดไว้และที่จะประกาศเพิ่มเติมในอนาคต ดังนี้</p>
        <ol>
            <li>ไม่ยอมให้ผู้อื่นมาร่วมใช้บัญชีสำหรับผู้ดูแลเว็บไซต์ของหน่วยงานตามที่ได้รับจากสำนักคอมพิวเตอร์</li>
            <li>ไม่ใช้บริการนี้ไปในทางเสื่อมเสีย หรือทำให้ผู้อื่นถูกละเมิดหรือได้รับความเดือดร้อน</li>
            <li>ไม่ใช้บริการนี้เพื่อผลประโยชน์ทางการค้าทั้งเพื่อตนเอง หรือเพื่อบุคคลอื่นใดทั้งสิ้น</li>
        </ol>
        <p>ข้าพเจ้าจะรับผิดชอบในการนำแฟ้มข้อมูลต่างๆ ไปเก็บบน Web Server ของมหาวิทยาลัยใน Directory ของส่วนงานตามที่ได้รับมอบหมาย</p>
    </section>

    <section class="website-signatures">
        <div><h2>สำหรับผู้ดูแลเว็บไซต์</h2><p>ลงชื่อ <span>{{ $contactName }}</span></p><p>ตำแหน่ง <span>{{ $text('contact_position') }}</span></p><p>วันที่ <span>{{ $thaiDate($record['submitted_at'] ?? now()) }}</span></p></div>
        <div><h2>สำหรับผู้รับรอง คณบดี/ผู้อำนวยการ</h2><p>ลงชื่อ <span>{{ $deanApproved ? $text('dean_name') : '' }}</span></p><p>ตำแหน่ง <span>{{ $deanApproved ? $text('dean_position') : '' }}</span></p><p>วันที่ <span>{{ $deanApproved ? $thaiDate($record['dean_decided_at'] ?? null) : '' }}</span></p></div>
    </section>

    <section class="website-staff">
        <h2>สำนักคอมพิวเตอร์</h2>
        <div class="website-staff__section">
            <strong>สำหรับเจ้าหน้าที่รับเอกสาร</strong>
            <p class="website-staff__signature"><b>ลงชื่อ</b><span>{{ $officerReviewed ? $text('officer_name') : '' }}</span><b>วันที่รับเอกสาร</b><span>{{ $officerReviewed ? $text('officer_reviewed_at') : '' }}</span></p>
        </div>
        <div class="website-staff__section">
            <strong>สำหรับผู้พิจารณา</strong>
            <p class="website-staff__decisions">
                <span><span class="document-checkbox {{ $officerPassed ? 'is-checked' : '' }}" aria-hidden="true"></span> อนุมัติ</span>
                <span><span class="document-checkbox {{ $officerRejected ? 'is-checked' : '' }}" aria-hidden="true"></span> ไม่อนุมัติ</span>
            </p>
            @if($officerRejected)
            <p class="website-staff__reason"><b>เหตุผล</b><span>{{ $text('reason') }}</span></p>
            @endif
            <p class="website-staff__signature"><b>ลงชื่อ</b><span>{{ $officerReviewed ? $text('officer_name') : '' }}</span><b>วันที่พิจารณา</b><span>{{ $officerReviewed ? $text('officer_reviewed_at') : '' }}</span></p>
        </div>
        <div class="website-staff__section">
            <strong>สำหรับเจ้าหน้าที่ดำเนินการ</strong>
            <p class="website-staff__domain website-staff__domain--allocation"><b>ผลการดำเนินการ</b><span>{{ $text('allocated_domain') }}</span></p>
            <p class="website-staff__signature"><b>ลงชื่อ</b><span>{{ $officerPassed ? $text('officer_name') : '' }}</span><b>วันที่ดำเนินการ</b><span>{{ $officerPassed ? $text('officer_reviewed_at') : '' }}</span></p>
        </div>
    </section>
</main>
</body>
</html>
