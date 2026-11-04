<?php

namespace Tests\Unit;

use App\Http\Controllers\PortalController;
use App\Models\CourseRequest;
use App\Services\DepartmentDirectory;
use App\Services\DocumentPdfRenderer;
use App\Services\PersonnelDirectory;
use App\Services\PortalIdentityService;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class PortalControllerRoleRecipientsTest extends TestCase
{
    public function test_local_form_head_uses_the_configured_test_recipient_without_an_assignment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        DB::shouldReceive('connection')->never();

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            Mockery::mock(DocumentPdfRenderer::class),
            Mockery::mock(PersonnelDirectory::class),
            Mockery::mock(PortalIdentityService::class),
        );
        $method = new ReflectionMethod($controller, 'formResponsibilityRecipients');
        $request = new CourseRequest(['form_type_code' => 'CC-ITED-01']);

        $this->assertSame([
            ['pers_id' => 713796, 'email' => 'pakteema@g.swu.ac.th'],
        ], $method->invoke($controller, $request, 'head'));
    }

    public function test_role_recipients_include_every_active_assignment(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('table')->once()->with('service_portal.user_role_assignment as assignments')->andReturnSelf();
        $query->shouldReceive('join')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('roles.role_code', 'OFFICER')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('assignments.is_active', 'Y')->andReturnSelf();
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
            Mockery::mock(DocumentPdfRenderer::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );
        $method = new ReflectionMethod($controller, 'roleRecipients');

        $this->assertSame([
            ['pers_id' => 101, 'email' => 'officer101@g.swu.ac.th'],
            ['pers_id' => 202, 'email' => 'officer202@g.swu.ac.th'],
        ], $method->invoke($controller, 'OFFICER'));
    }

    public function test_form_officer_recipients_only_include_assignments_for_the_request_form(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('table')->once()->with('service_portal.form_officer_assignment')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('form_type_code', 'CC-ITED-06')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('is_active', 'Y')->andReturnSelf();
        $query->shouldReceive('orderBy')->once()->with('officer_assignment_id')->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('officer_pers_id')->andReturn(collect([202]));
        DB::shouldReceive('connection')->once()->with('course133')->andReturn($query);

        $person = (object) ['person_id' => 202];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('findByPersonId')->once()->with(202)->andReturn($person);
        $directory->shouldReceive('email')->once()->with($person)->andReturn('officer202@g.swu.ac.th');

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            Mockery::mock(DocumentPdfRenderer::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );
        $method = new ReflectionMethod($controller, 'formResponsibilityRecipients');
        $request = new CourseRequest(['form_type_code' => 'CC-ITED-06']);

        $this->assertSame([
            ['pers_id' => 202, 'email' => 'officer202@g.swu.ac.th'],
        ], $method->invoke($controller, $request, 'officer'));
    }
}
