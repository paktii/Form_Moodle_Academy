<?php

namespace Tests\Unit;

use App\Services\DocumentPdfRenderer;
use ReflectionMethod;
use Tests\TestCase;

class DocumentPdfRendererDeanPositionTest extends TestCase
{
    public function test_acting_dean_position_is_labelled_as_acting_for_the_official_position(): void
    {
        $this->assertSame(
            'รักษาการแทนคณบดีคณะแพทยศาสตร์',
            $this->deanPositionForPdf([
                'dean_position' => 'คณบดีคณะแพทยศาสตร์',
                'dean_is_acting' => true,
            ]),
        );
    }

    public function test_existing_acting_dean_position_is_not_prefixed_twice(): void
    {
        $this->assertSame(
            'รักษาการแทนคณบดีคณะแพทยศาสตร์',
            $this->deanPositionForPdf([
                'dean_position' => 'รักษาการแทนคณบดีคณะแพทยศาสตร์',
                'dean_is_acting' => true,
            ]),
        );
    }

    public function test_official_dean_position_is_unchanged(): void
    {
        $this->assertSame(
            'คณบดีคณะแพทยศาสตร์',
            $this->deanPositionForPdf([
                'dean_position' => 'คณบดีคณะแพทยศาสตร์',
                'dean_is_acting' => false,
            ]),
        );
    }

    private function deanPositionForPdf(array $record): ?string
    {
        $method = new ReflectionMethod(DocumentPdfRenderer::class, 'deanPositionForPdf');

        return $method->invoke(new DocumentPdfRenderer(), $record);
    }
}
