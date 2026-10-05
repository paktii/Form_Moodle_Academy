<?php

namespace Tests\Unit;

use App\Services\PersonnelDirectory;
use App\Services\SwuMisApiClient;
use Mockery;
use Tests\TestCase;

class PersonnelDirectoryTest extends TestCase
{
    public function test_email_uses_google_workspace_account_when_available(): void
    {
        $person = (object) [
            'buasri_id' => 'requester',
            'gafe_account' => 'workspace.user',
        ];

        $this->assertSame('workspace.user@g.swu.ac.th', $this->directory()->email($person));
    }

    public function test_email_falls_back_to_buasri_id_when_google_workspace_field_is_empty(): void
    {
        $person = (object) [
            'buasri_id' => 'requester',
            'gafe_account' => null,
        ];

        $this->assertSame('requester@g.swu.ac.th', $this->directory()->email($person));
    }

    public function test_coordinator_defaults_use_available_officer_api_fields(): void
    {
        $person = (object) [
            'buasri_id' => 'requester',
            'gafe_account' => '',
            'prename_lname_th' => '',
            'person_fname_th' => 'สมหญิง',
            'person_lname_th' => 'ทดสอบ',
            'position' => 'นักวิชาการ',
            'in_telephone_no' => '',
            'did_phone_no' => '',
            'mobile_phone' => '',
        ];

        $this->assertSame([
            'coordinator_first' => 'สมหญิง',
            'coordinator_last' => 'ทดสอบ',
            'coordinator_position' => 'นักวิชาการ',
            'coordinator_phone' => '',
            'coordinator_email' => 'requester@g.swu.ac.th',
        ], $this->directory()->coordinatorDefaults($person));
    }

    public function test_active_person_is_mapped_from_the_officer_api(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('search')
            ->once()
            ->with('Officer', 'buasri', 'requester')
            ->andReturn([[
                'personId' => '123',
                'buasriId' => 'requester',
                'gafeMail' => 'requester@g.swu.ac.th',
                'titleTh' => 'นางสาว',
                'nameTh' => 'สมหญิง',
                'lnameTh' => 'ทดสอบ',
                'facultyId' => '5000',
                'departmentId' => '5100',
                'departmentTh' => 'หน่วยงานทดสอบ',
                'positionTh' => 'นักวิชาการ',
                'activeFlag' => 'A',
            ]]);

        $person = (new PersonnelDirectory($api))->findActiveByBuasriId('Requester');

        $this->assertNotNull($person);
        $this->assertSame(123, $person->person_id);
        $this->assertSame(5100, $person->dept_cd);
        $this->assertSame(5000, $person->in_dept_cd);
        $this->assertSame('หน่วยงานทดสอบ', $person->division);
    }

    private function directory(): PersonnelDirectory
    {
        return new PersonnelDirectory(Mockery::mock(SwuMisApiClient::class));
    }
}
