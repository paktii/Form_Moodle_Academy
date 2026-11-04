<?php

namespace Tests\Unit;

use App\Services\LdapAuthenticator;
use App\Services\PersonnelDirectory;
use App\Services\PortalIdentityService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PortalIdentityServiceFormOfficerTest extends TestCase
{
    public function test_active_form_officer_assignment_logs_in_as_officer(): void
    {
        $database = Mockery::mock();
        $database->shouldReceive('table')
            ->once()
            ->with('service_portal.user_role_assignment as assignments')
            ->andReturnSelf();
        $database->shouldReceive('join')->once()->andReturnSelf();
        $database->shouldReceive('where')->once()->with('assignments.pers_id', 202)->andReturnSelf();
        $database->shouldReceive('where')->once()->with('assignments.is_active', 'Y')->andReturnSelf();
        $database->shouldReceive('pluck')->once()->with('roles.role_code')->andReturn(collect());
        $database->shouldReceive('table')
            ->once()
            ->with('service_portal.form_officer_assignment')
            ->andReturnSelf();
        $database->shouldReceive('where')->once()->with('officer_pers_id', 202)->andReturnSelf();
        $database->shouldReceive('where')->once()->with('is_active', 'Y')->andReturnSelf();
        $database->shouldReceive('exists')->once()->andReturnTrue();
        DB::shouldReceive('connection')->once()->with('course133')->andReturn($database);

        $ldap = Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')->once()->with('officer', 'secret')->andReturnTrue();

        $person = (object) [
            'person_id' => 202,
            'dept_cd' => 6800,
            'in_dept_cd' => null,
            'buasri_id' => 'officer',
        ];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('findActiveByBuasriId')->once()->with('officer')->andReturn($person);
        $directory->shouldReceive('email')->once()->with($person)->andReturn('officer@g.swu.ac.th');
        $directory->shouldReceive('name')->once()->with($person)->andReturn('เจ้าหน้าที่ทดสอบ');

        $actor = (new PortalIdentityService($ldap, $directory))->authenticate('officer', 'secret');

        $this->assertSame('officer', $actor['role']);
        $this->assertSame('เจ้าหน้าที่', $actor['label']);
        $this->assertSame(['user', 'officer'], $actor['available_roles']);
    }
}

