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
    public function test_local_website_form_does_not_inject_the_test_email_recipient_as_a_dean(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);

        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldNotReceive('findByPersonId');
        $directory->shouldNotReceive('email');

        $controller = new PortalController(
            Mockery::mock(DepartmentDirectory::class),
            Mockery::mock(DocumentPdfRenderer::class),
            $directory,
            Mockery::mock(PortalIdentityService::class),
        );

        $idsMethod = new ReflectionMethod($controller, 'activeWebsiteDeanPersonIds');
        $optionsMethod = new ReflectionMethod($controller, 'websiteDeanOptionsForTargetDepartment');

        $this->assertSame([], $idsMethod->invoke($controller, 0));
        $this->assertSame([], $optionsMethod->invoke($controller, 0));
    }
}
