<?php

namespace Tests\Unit;

use App\Services\DepartmentDirectory;
use App\Services\SwuMisApiClient;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DepartmentDirectoryTest extends TestCase
{
    public function test_department_api_rows_are_split_into_parent_and_child_options(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('all')->once()->with('Department')->andReturn([
            [
                'deptCd' => '5900',
                'indeptCd' => '5900',
                'deptLnameTh' => 'สำนักคอมพิวเตอร์',
                'activeFlag' => 'Y',
            ],
            [
                'deptCd' => '5901',
                'indeptCd' => '5900',
                'deptLnameTh' => 'งานระบบสารสนเทศ',
                'activeFlag' => 'Y',
            ],
            [
                'deptCd' => '5999',
                'indeptCd' => '5900',
                'deptLnameTh' => 'หน่วยงานยกเลิก',
                'activeFlag' => 'N',
            ],
        ]);

        $directory = new DepartmentDirectory($api);

        $this->assertSame('สำนักคอมพิวเตอร์', $directory->options()->get(5900));
        $this->assertSame('งานระบบสารสนเทศ', $directory->subdepartmentOptions()->get(5901));
        $this->assertSame(5900, $directory->subdepartmentParents()->get(5901));
        $this->assertFalse($directory->subdepartmentOptions()->has(5999));
    }

    public function test_department_api_failure_is_not_replaced_with_local_fallback_data(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('all')->once()->with('Department')->andThrow(new RuntimeException('API unavailable'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API unavailable');

        (new DepartmentDirectory($api))->options();
    }

    public function test_empty_department_api_response_is_rejected(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('all')->once()->with('Department')->andReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SWU MIS Department API returned no active parent departments.');

        (new DepartmentDirectory($api))->options();
    }
}
