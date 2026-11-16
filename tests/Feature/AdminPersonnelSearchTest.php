<?php

namespace Tests\Feature;

use App\Services\PersonnelDirectory;
use Mockery;
use Tests\TestCase;

class AdminPersonnelSearchTest extends TestCase
{
    public function test_admin_can_search_and_identify_personnel_before_assignment(): void
    {
        $person = (object) [
            'person_id' => 713796,
            'buasri_id' => 'pakteema',
            'division' => 'สำนักคอมพิวเตอร์',
        ];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('searchActive')->once()->with('ภัคธีมา')->andReturn([$person]);
        $directory->shouldReceive('name')->once()->with($person)->andReturn('นางสาว ภัคธีมา รัตนคช');
        $this->app->instance(PersonnelDirectory::class, $directory);

        $this->withSession(['portal.actor' => [
            'pers_id' => 713796,
            'buasri_id' => 'pakteema',
            'name' => 'นางสาว ภัคธีมา รัตนคช',
            'label' => 'ผู้ดูแลระบบ',
            'role' => 'admin',
        ]])->getJson('/admin/personnel/search?q='.rawurlencode('ภัคธีมา'))
            ->assertOk()
            ->assertJsonPath('results.0.person_id', 713796)
            ->assertJsonPath('results.0.buasri_id', 'pakteema')
            ->assertJsonPath('results.0.department', 'สำนักคอมพิวเตอร์');
    }

    public function test_non_admin_cannot_search_personnel(): void
    {
        $this->withSession(['portal.actor' => [
            'pers_id' => 100,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'label' => 'ผู้ใช้งาน',
            'role' => 'user',
        ]])->getJson('/admin/personnel/search?q=test')->assertForbidden();
    }

    public function test_personnel_search_includes_cross_department_appointments(): void
    {
        $matching = (object) [
            'person_id' => 713796,
            'buasri_id' => 'pakteema',
            'division' => 'คณะอื่น',
            'dept_cd' => 6801,
            'in_dept_cd' => 6800,
        ];
        $directory = Mockery::mock(PersonnelDirectory::class);
        $directory->shouldReceive('searchActive')->once()->with('ภัคธีมา')->andReturn([$matching]);
        $directory->shouldReceive('name')->once()->with($matching)->andReturn('นางสาว ภัคธีมา รัตนคช');
        $this->app->instance(PersonnelDirectory::class, $directory);

        $this->withSession(['portal.actor' => [
            'pers_id' => 713796,
            'buasri_id' => 'pakteema',
            'name' => 'นางสาว ภัคธีมา รัตนคช',
            'label' => 'ผู้ดูแลระบบ',
            'role' => 'admin',
        ]])->getJson('/admin/personnel/search?q='.rawurlencode('ภัคธีมา').'&target_dept_id=5900')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.person_id', 713796)
            ->assertJsonPath('results.0.department', 'คณะอื่น');
    }
}
