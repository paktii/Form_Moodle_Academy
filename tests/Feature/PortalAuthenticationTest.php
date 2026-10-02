<?php

namespace Tests\Feature;

use App\Services\PortalIdentityService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    public function test_protected_pages_require_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/officer/reviews')->assertRedirect('/login');
        $this->get('/approver/reviews')->assertRedirect('/login');
    }

    #[DataProvider('directoryRoles')]
    public function test_each_directory_role_can_log_in(string $role, string $destination): void
    {
        $actor = [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'บุคลากร',
            'role' => $role,
        ];
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('someone', 'secret')->andReturn($actor);
        $this->app->instance(PortalIdentityService::class, $identity);

        $response = $this->post('/login', [
            'buasri_id' => 'someone@swu.ac.th',
            'password' => 'secret',
        ]);

        $response->assertRedirect($destination);
        $this->assertSame($role, session('portal.actor.role'));
    }

    public static function directoryRoles(): array
    {
        return [
            'requester' => ['user', '/'],
            'officer' => ['officer', '/officer/reviews'],
            'approver' => ['approver', '/approver/reviews'],
        ];
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('someone', 'wrong-password')->andReturnNull();
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->post('/login', [
            'buasri_id' => 'someone',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('buasri_id');

        $this->assertNull(session('portal.actor'));
    }

    public function test_login_is_rate_limited_after_five_attempts_per_buasri_id_and_ip(): void
    {
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->times(5)->with('someone', 'wrong-password')->andReturnNull();
        $this->app->instance(PortalIdentityService::class, $identity);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post('/login', [
                'buasri_id' => 'someone',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('buasri_id');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post('/login', [
            'buasri_id' => 'someone',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_development_role_switcher_uses_selected_assigned_role(): void
    {
        config()->set('course-workflow.dev_role_switcher', true);
        $actor = [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'ผู้อนุมัติ',
            'role' => 'approver',
            'available_roles' => ['user', 'officer', 'approver'],
        ];
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('someone', 'secret')->andReturn($actor);
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->post('/login', [
            'buasri_id' => 'someone',
            'password' => 'secret',
            'login_role' => 'officer',
        ])->assertRedirect('/officer/reviews');

        $this->assertSame('officer', session('portal.actor.role'));
        $this->assertArrayNotHasKey('available_roles', session('portal.actor'));
    }

    public function test_development_role_switcher_rejects_unassigned_role(): void
    {
        config()->set('course-workflow.dev_role_switcher', true);
        $actor = [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'บุคลากร',
            'role' => 'user',
            'available_roles' => ['user'],
        ];
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('someone', 'secret')->andReturn($actor);
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->post('/login', [
            'buasri_id' => 'someone',
            'password' => 'secret',
            'login_role' => 'approver',
        ])->assertSessionHasErrors('login_role');

        $this->assertNull(session('portal.actor'));
    }

    public function test_configured_staff_login_fallback_is_not_available(): void
    {
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('officer', 'officer-secret')->andReturnNull();
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->post('/login', [
            'buasri_id' => 'officer',
            'password' => 'officer-secret',
        ])->assertSessionHasErrors('buasri_id');

        $this->assertNull(session('portal.actor'));
    }

    public function test_role_cannot_open_another_roles_pages(): void
    {
        $this->withSession(['portal.actor' => [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'บุคลากร',
            'role' => 'user',
        ]])->get('/officer/reviews')->assertForbidden();
    }

    public function test_unknown_login_role_is_not_found(): void
    {
        $this->get('/login/invalid')->assertNotFound();
    }
}
