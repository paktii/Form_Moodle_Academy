@php
    $regularFont = 'data:font/truetype;base64,'.base64_encode(file_get_contents(resource_path('fonts/Sarabun-Regular.ttf')));
    $boldFont = 'data:font/truetype;base64,'.base64_encode(file_get_contents(resource_path('fonts/Sarabun-Bold.ttf')));
    $pdfCss = file_get_contents(resource_path('css/pages/document-pdf.css'));
    $pdfCss = str_replace(
        ['../../fonts/Sarabun-Regular.ttf', '../../fonts/Sarabun-Bold.ttf'],
        [$regularFont, $boldFont],
        $pdfCss
    );
    $documentLogo = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('img/logo-pdf.jpg')));
    $styleTag = '<'.'style>' . $pdfCss . '</'.'style>';
    $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
    $requestCode = \Illuminate\Support\Str::afterLast($requestNumber, '-');
    $projectName = (string) ($record['project_name'] ?? 'โครงการขออนุมัติ');
    $safeProjectName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $projectName);
    $pdfTitle = $requestCode !== '' ? "CC-ITED-06_{$requestCode}.pdf" : "{$safeProjectName}.pdf";
@endphp
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $pdfTitle }}</title>
    {!! $styleTag !!}
</head>
<body>
    @include('portal.partials.document-content', [
        'record' => $record,
        'documentLogo' => $documentLogo,
    ])
</body>
</html>
