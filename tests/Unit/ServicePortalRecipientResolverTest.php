<?php

namespace Tests\Unit;

use App\Models\ServicePortal\ServiceRequest;
use App\Services\PersonnelDirectory;
use App\Services\ServicePortalRecipientResolver;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ServicePortalRecipientResolverTest extends TestCase
{
    public function test_local_and_uat_form_head_uses_the_active_admin_assignment(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('table')->twice()->with('service_portal.form_head_assignment')->andReturnSelf();
        $query->shouldReceive('where')->twice()->with('form_type_code', 'CC-ITED-01')->andReturnSelf();
        $query->shouldReceive('where')->twice()->with('is_active', 'Y')->andReturnSelf();
        $query->shouldReceive('pluck')->twice()->with('head_pers_id')->andReturn(collect([701341]));
        DB::shouldReceive('connection')->twice()->with('course133')->andReturn($query);

        $person = (object) ['person_id' => 701341];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('findByPersonId')->twice()->with(701341)->andReturn($person);
        $directory->shouldReceive('email')->twice()->with($person)->andReturn('suwimon@g.swu.ac.th');
        $directory->shouldReceive('name')->twice()->with($person)->andReturn('นางสาว สุวิมล คงศักดิ์ตระกูล');

        $resolver = new ServicePortalRecipientResolver($directory);
        $request = new ServiceRequest([
            'form_type_code' => 'CC-ITED-01',
            'requester_pers_id' => 701341,
        ]);

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame([[
                'pers_id' => 701341,
                'email' => 'suwimon@g.swu.ac.th',
                'name' => 'นางสาว สุวิมล คงศักดิ์ตระกูล',
            ]], $resolver->formHeadRecipients($request));
        }
    }

    public function test_uat_form_head_uses_the_requesting_tester_when_a_head_is_assigned(): void
    {
        $this->app->detectEnvironment(fn () => 'uat');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('table')->once()->with('service_portal.form_head_assignment')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('form_type_code', 'CC-ITED-02')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('is_active', 'Y')->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('head_pers_id')->andReturn(collect([713796]));
        DB::shouldReceive('connection')->once()->with('course133')->andReturn($query);

        $people = [
            713796 => (object) ['person_id' => 713796],
            701341 => (object) ['person_id' => 701341],
        ];
        $directory = Mockery::mock(PersonnelDirectory::class);
        foreach ($people as $personId => $person) {
            $email = $personId === 713796 ? 'pakteema@g.swu.ac.th' : 'suwimon@g.swu.ac.th';
            $name = $personId === 713796 ? 'นางสาว ภัคธีมา รัตนเดช' : 'นางสาว สุวิมล คงศักดิ์ตระกูล';
            $directory->shouldReceive('findByPersonId')->once()->with($personId)->andReturn($person);
            $directory->shouldReceive('email')->once()->with($person)->andReturn($email);
            $directory->shouldReceive('name')->once()->with($person)->andReturn($name);
        }

        $resolver = new ServicePortalRecipientResolver($directory);
        $request = new ServiceRequest([
            'form_type_code' => 'CC-ITED-02',
            'requester_pers_id' => 701341,
        ]);

        $this->assertSame([[
            'pers_id' => 701341,
            'email' => 'suwimon@g.swu.ac.th',
            'name' => 'นางสาว สุวิมล คงศักดิ์ตระกูล',
        ]], $resolver->formHeadRecipients($request));
    }
}
