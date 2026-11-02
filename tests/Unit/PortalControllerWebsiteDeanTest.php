<?php

namespace Tests\Unit;

use App\Http\Controllers\PortalController;
use App\Services\DepartmentDirectory;
use App\Services\DocumentPdfRenderer;
use App\Services\PersonnelDirectory;
use App\Services\PortalIdentityService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class PortalControllerWebsiteDeanTest extends TestCase
{
    public function test_local_website_form_includes_the_configured_test_recipient_option(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);

        $person = (object) ['person_id' => 713796];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('findByPersonId')->times(3)->with(713796)->andReturn($person);
        $directory->shouldReceive('email')->twice()->with($person)->andReturn('pakteema@g.swu.ac.th');
        $directory->shouldReceive('name')->once()->with($person)->andReturn('นางสาว ภัคธีมา รัตนคช');

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            Mockery::mock(DocumentPdfRenderer::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );

        $idsMethod = new ReflectionMethod($controller, 'activeWebsiteDeanPersonIds');
        $optionsMethod = new ReflectionMethod($controller, 'websiteDeanOptionsForTargetDepartment');

        $this->assertSame([713796], $idsMethod->invoke($controller, 0));
        $this->assertSame([
            713796 => 'นางสาว ภัคธีมา รัตนคช (pakteema@g.swu.ac.th)',
        ], $optionsMethod->invoke($controller, 0));
    }

    public function test_local_website_form_rejects_a_mismatched_test_recipient_identity(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('course-workflow.notifications.test_recipient_email', 'someone-else@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);

        $person = (object) ['person_id' => 713796];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('findByPersonId')->once()->with(713796)->andReturn($person);
        $directory->shouldReceive('email')->once()->with($person)->andReturn('pakteema@g.swu.ac.th');

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            Mockery::mock(DocumentPdfRenderer::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );

        $method = new ReflectionMethod($controller, 'activeWebsiteDeanPersonIds');

        $this->assertSame([], $method->invoke($controller, 0));
    }
}
