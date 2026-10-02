<?php

namespace Tests\Unit;

use App\Models\SalaPerson;
use App\Services\PersonnelDirectory;
use PHPUnit\Framework\TestCase;

class PersonnelDirectoryTest extends TestCase
{
    public function test_email_uses_google_workspace_account_when_available(): void
    {
        $person = new SalaPerson([
            'buasri_id' => 'requester',
            'gafe_account' => 'workspace.user',
        ]);

        $this->assertSame('workspace.user@g.swu.ac.th', (new PersonnelDirectory)->email($person));
    }

    public function test_email_falls_back_to_buasri_id_when_google_workspace_field_is_empty(): void
    {
        $person = new SalaPerson([
            'buasri_id' => 'requester',
            'gafe_account' => null,
        ]);

        $this->assertSame('requester@g.swu.ac.th', (new PersonnelDirectory)->email($person));
    }

    public function test_coordinator_defaults_use_personnel_data_and_prefer_internal_phone(): void
    {
        $person = new SalaPerson([
            'buasri_id' => 'requester',
            'person_fname_th' => 'สมหญิง',
            'person_lname_th' => 'ทดสอบ',
            'position' => 'นักวิชาการ',
            'in_telephone_no' => '15045',
            'did_phone_no' => '02-000-0000',
            'mobile_phone' => '080-000-0000',
        ]);

        $this->assertSame([
            'coordinator_first' => 'สมหญิง',
            'coordinator_last' => 'ทดสอบ',
            'coordinator_position' => 'นักวิชาการ',
            'coordinator_phone' => '15045',
            'coordinator_email' => 'requester@g.swu.ac.th',
        ], (new PersonnelDirectory)->coordinatorDefaults($person));
    }

    public function test_coordinator_defaults_fall_back_to_mobile_phone(): void
    {
        $person = new SalaPerson([
            'buasri_id' => 'requester',
            'in_telephone_no' => '',
            'did_phone_no' => null,
            'mobile_phone' => '080-000-0000',
        ]);

        $this->assertSame('080-000-0000', (new PersonnelDirectory)->coordinatorDefaults($person)['coordinator_phone']);
    }
}
