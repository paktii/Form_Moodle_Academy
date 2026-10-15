<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeanPdfSignatureVisibilityTest extends TestCase
{
    public function test_dean_identity_is_hidden_until_the_request_is_approved(): void
    {
        foreach ([null, 'REJECTED'] as $decision) {
            $html = view('portal.partials.document-content', [
                'record' => $this->record($decision),
            ])->render();

            $this->assertStringNotContainsString('ชื่อคณบดีทดสอบ', $html);
            $this->assertStringNotContainsString('ตำแหน่งคณบดีทดสอบ', $html);
            $this->assertStringNotContainsString('14 ตุลาคม 2569', $html);
        }
    }

    public function test_dean_identity_is_shown_after_the_request_is_approved(): void
    {
        $html = view('portal.partials.document-content', [
            'record' => $this->record('APPROVED'),
        ])->render();

        $this->assertStringContainsString('ชื่อคณบดีทดสอบ', $html);
        $this->assertStringContainsString('ตำแหน่งคณบดีทดสอบ', $html);
        $this->assertStringContainsString('14 ตุลาคม 2569', $html);
    }

    private function record(?string $decision): array
    {
        return [
            'submitted_at' => '2026-01-01',
            'dean_name' => 'ชื่อคณบดีทดสอบ',
            'dean_position' => 'ตำแหน่งคณบดีทดสอบ',
            'dean_decision' => $decision,
            'dean_decided_at' => '2026-10-14',
        ];
    }
}
