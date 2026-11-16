<?php

namespace Tests\Unit;

use App\Services\PersonnelDirectory;
use App\Services\SwuMisApiClient;
use Illuminate\Support\Facades\Cache;
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

    public function test_email_never_returns_a_non_google_workspace_domain(): void
    {
        $person = (object) [
            'buasri_id' => 'requester@swu.ac.th',
            'gafe_account' => 'requester@example.com',
        ];

        $this->assertNull($this->directory()->email($person));
    }

    public function test_email_uses_buasri_workspace_address_when_gafe_domain_is_not_allowed(): void
    {
        $person = (object) [
            'buasri_id' => 'requester',
            'gafe_account' => 'requester@example.com',
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

    public function test_active_person_can_be_found_by_exact_full_name(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('search')
            ->once()
            ->with('Officer', 'fullname', 'ภัคธีมา รัตนคช')
            ->andReturn([
                [
                    'personId' => '713796', 'buasriId' => 'pakteema', 'titleTh' => 'นางสาว',
                    'nameTh' => 'ภัคธีมา', 'lnameTh' => 'รัตนคช', 'activeFlag' => 'A',
                ],
                [
                    'personId' => '712848', 'buasriId' => 'paktheema', 'titleTh' => 'นางสาว',
                    'nameTh' => 'ภัคธีมา', 'lnameTh' => 'ธาดา', 'activeFlag' => 'A',
                ],
            ]);

        $person = (new PersonnelDirectory($api))->findActiveByName('ภัคธีมา รัตนคช');

        $this->assertNotNull($person);
        $this->assertSame(713796, $person->person_id);
        $this->assertSame('pakteema', $person->buasri_id);
    }

    public function test_ambiguous_active_name_is_not_selected_automatically(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('search')->once()->andReturn([
            ['personId' => '1', 'buasriId' => 'one', 'nameTh' => 'สมชาย', 'lnameTh' => 'หนึ่ง', 'activeFlag' => 'A'],
            ['personId' => '2', 'buasriId' => 'two', 'nameTh' => 'สมชาย', 'lnameTh' => 'สอง', 'activeFlag' => 'A'],
        ]);

        $this->assertNull((new PersonnelDirectory($api))->findActiveByName('สมชาย'));
    }

    public function test_full_name_search_falls_back_to_last_name_when_api_does_not_match_spaces(): void
    {
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('search')->once()->with('Officer', 'fullname', 'ภัคธีมา รัตนคช')->andReturn([]);
        $api->shouldReceive('search')->once()->with('Officer', 'fullname', 'รัตนคช')->andReturn([
            ['personId' => '712135', 'buasriId' => 'paweesudar', 'nameTh' => 'ปวีณ์สุดา', 'lnameTh' => 'รัตนคช', 'activeFlag' => 'A'],
            ['personId' => '713796', 'buasriId' => 'pakteema', 'nameTh' => 'ภัคธีมา', 'lnameTh' => 'รัตนคช', 'activeFlag' => 'A'],
        ]);

        $person = (new PersonnelDirectory($api))->findActiveByName('ภัคธีมา รัตนคช');

        $this->assertSame(713796, $person?->person_id);
    }

    public function test_department_search_matches_partial_name_locally(): void
    {
        Cache::forget('swu-mis:officers:faculty:5900');
        $api = Mockery::mock(SwuMisApiClient::class);
        $api->shouldReceive('searchAll')->once()->with('Officer', 'facultyid', '5900')->andReturn([
            [
                'personId' => '713796', 'buasriId' => 'pakteema', 'titleTh' => 'นางสาว',
                'nameTh' => 'ภัคธีมา', 'lnameTh' => 'รัตนคช', 'activeFlag' => 'A',
                'facultyId' => '5900', 'departmentId' => '5906', 'departmentTh' => 'ส่วนเทคโนโลยีสารสนเทศ',
            ],
            [
                'personId' => '700001', 'buasriId' => 'someone', 'nameTh' => 'สมชาย',
                'lnameTh' => 'ทดสอบ', 'activeFlag' => 'A', 'facultyId' => '5900', 'departmentId' => '5901',
            ],
        ]);

        $people = (new PersonnelDirectory($api))->searchActiveInDepartment(5900, 'ภัค');

        $this->assertCount(1, $people);
        $this->assertSame(713796, $people[0]->person_id);
    }

    private function directory(): PersonnelDirectory
    {
        return new PersonnelDirectory(Mockery::mock(SwuMisApiClient::class));
    }
}
