<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DocumentPdfRenderer
{
    public function render(array $record): string
    {
        $workingDirectory = storage_path('framework/pdf/'.Str::uuid());
        File::ensureDirectoryExists($workingDirectory);

        $htmlPath = $workingDirectory.DIRECTORY_SEPARATOR.'document.html';
        $pdfPath = $workingDirectory.DIRECTORY_SEPARATOR.'document.pdf';
        $profilePath = $workingDirectory.DIRECTORY_SEPARATOR.'browser-profile';

        try {
            File::put($htmlPath, view('portal.document-pdf', compact('record'))->render());

            $process = new Process([
                $this->browserPath(),
                '--headless=new',
                '--disable-gpu',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-default-apps',
                '--hide-scrollbars',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--run-all-compositor-stages-before-draw',
                '--user-data-dir='.$profilePath,
                '--print-to-pdf='.$pdfPath,
                $this->fileUrl($htmlPath),
            ]);
            $process->setTimeout((float) config('document-pdf.timeout', 60));
            $process->mustRun();

            abort_unless(File::isFile($pdfPath), 500, 'ไม่สามารถสร้างเอกสาร PDF ได้');
            $contents = File::get($pdfPath);
            abort_unless(str_starts_with($contents, '%PDF-'), 500, 'ไฟล์ PDF ที่สร้างไม่สมบูรณ์');

            return $contents;
        } finally {
            $this->deleteWorkingDirectory($workingDirectory);
        }
    }

    private function deleteWorkingDirectory(string $workingDirectory): void
    {
        for ($attempt = 0; $attempt < 5 && File::isDirectory($workingDirectory); $attempt++) {
            File::deleteDirectory($workingDirectory);

            if (File::isDirectory($workingDirectory)) {
                usleep(100_000);
            }
        }
    }

    private function browserPath(): string
    {
        $configuredPath = config('document-pdf.browser_path');
        $candidates = array_filter([
            is_string($configuredPath) ? trim($configuredPath) : null,
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('ไม่พบ Chrome หรือ Edge สำหรับสร้างเอกสาร PDF');
    }

    private function fileUrl(string $path): string
    {
        return 'file:///'.str_replace(['\\', ' '], ['/', '%20'], $path);
    }
}
