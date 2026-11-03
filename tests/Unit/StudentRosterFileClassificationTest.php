<?php

namespace Tests\Unit;

use App\Http\Controllers\PortalController;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class StudentRosterFileClassificationTest extends TestCase
{
    public function test_spreadsheet_is_not_treated_as_roster_without_explicit_selection(): void
    {
        $this->assertFalse($this->shouldStoreAsRoster([
            'additional_name' => 'เอกสารประกอบ.csv',
        ]));
    }

    public function test_only_selected_spreadsheet_is_treated_as_roster(): void
    {
        $this->assertTrue($this->shouldStoreAsRoster([
            'additional_name' => 'รายชื่อผู้เรียน.xlsx',
            'additional_is_student_roster' => true,
        ]));
        $this->assertFalse($this->shouldStoreAsRoster([
            'additional_name' => 'เอกสารประกอบ.csv',
            'additional_is_student_roster' => false,
        ]));
    }

    public function test_selected_non_spreadsheet_is_not_treated_as_roster(): void
    {
        $this->assertFalse($this->shouldStoreAsRoster([
            'additional_name' => 'เอกสารประกอบ.pdf',
            'additional_is_student_roster' => true,
        ]));
    }

    private function shouldStoreAsRoster(array $file): bool
    {
        $controller = (new ReflectionClass(PortalController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PortalController::class, 'shouldStoreAsStudentRoster');

        return $method->invoke($controller, $file, true);
    }
}
