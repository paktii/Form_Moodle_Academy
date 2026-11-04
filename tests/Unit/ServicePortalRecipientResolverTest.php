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
    public function test_local_form_head_uses_the_configured_test_recipient_without_an_assignment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        DB::shouldReceive('connection')->never();

        $resolver = new ServicePortalRecipientResolver(Mockery::mock(PersonnelDirectory::class));
        $request = new ServiceRequest(['form_type_code' => 'CC-ITED-01']);

        $this->assertSame([[
            'pers_id' => 713796,
            'email' => 'pakteema@g.swu.ac.th',
            'name' => 'ผู้รับแจ้งเตือน Head สำหรับทดสอบ',
        ]], $resolver->formHeadRecipients($request));
    }

    public function test_uat_form_head_uses_the_configured_test_recipient_without_an_assignment(): void
    {
        $this->app->detectEnvironment(fn () => 'uat');
        config()->set('course-workflow.notifications.test_recipient_person_id', 713796);
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        DB::shouldReceive('connection')->never();

        $resolver = new ServicePortalRecipientResolver(Mockery::mock(PersonnelDirectory::class));
        $request = new ServiceRequest(['form_type_code' => 'CC-ITED-06']);
        $recipients = $resolver->formHeadRecipients($request);

        $this->assertSame(713796, $recipients[0]['pers_id']);
        $this->assertSame('pakteema@g.swu.ac.th', $recipients[0]['email']);
    }
}
