<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudentRosterFileSelectionViewTest extends TestCase
{
    public function test_each_spreadsheet_can_be_explicitly_selected_as_the_student_roster(): void
    {
        $html = view('components.portal-multi-upload', [
            'name' => 'additional_documents',
            'id' => 'additional-documents',
            'form' => 'course-form',
            'files' => [
                ['additional_name' => 'เอกสารประกอบ.csv'],
                ['additional_name' => 'รายชื่อผู้เรียน.xlsx', 'additional_is_student_roster' => true],
                ['additional_name' => 'หนังสืออนุมัติ.pdf'],
            ],
            'studentRosterSelector' => true,
            'studentRosterEnabled' => true,
        ])->render();

        $this->assertSame(2, substr_count($html, 'name="student_roster_file"'));
        $this->assertStringContainsString('value="existing:0"', $html);
        $this->assertMatchesRegularExpression('/value="existing:1"[^>]*checked/', $html);
        $this->assertStringNotContainsString('value="existing:2"', $html);
        $this->assertSame(2, substr_count($html, 'ใช้เป็นรายชื่อผู้เรียน'));
    }
}
