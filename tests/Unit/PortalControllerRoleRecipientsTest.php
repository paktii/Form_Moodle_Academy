<?php

namespace Tests\Unit;

use App\Http\Controllers\PortalController;
use App\Services\DepartmentDirectory;
use App\Services\PersonnelDirectory;
use App\Services\PortalIdentityService;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class PortalControllerRoleRecipientsTest extends TestCase
{
    public function test_role_recipients_include_every_active_assignment(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('table')->once()->with('course133.user_role_assignment as assignments')->andReturnSelf();
        $query->shouldReceive('join')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('roles.role_code', 'OFFICER')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('assignments.is_active', true)->andReturnSelf();
        $query->shouldReceive('orderBy')->once()->with('assignments.role_assignment_id')->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('assignments.pers_id')->andReturn(collect([101, 202]));
        DB::shouldReceive('connection')->once()->with('course133')->andReturn($query);

        $people = [
            101 => (object) ['person_id' => 101],
            202 => (object) ['person_id' => 202],
        ];
        $directory = Mockery::mock(PersonnelDirectory::class);
        foreach ($people as $personId => $person) {
            $directory->shouldReceive('findByPersonId')->once()->with($personId)->andReturn($person);
            $directory->shouldReceive('email')->once()->with($person)->andReturn("officer{$personId}@g.swu.ac.th");
        }

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );
        $method = new ReflectionMethod($controller, 'roleRecipients');

        $this->assertSame([
            ['pers_id' => 101, 'email' => 'officer101@g.swu.ac.th'],
            ['pers_id' => 202, 'email' => 'officer202@g.swu.ac.th'],
        ], $method->invoke($controller, 'OFFICER'));
    }
}
