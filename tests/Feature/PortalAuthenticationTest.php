<?php

namespace Tests\Feature;

use App\Services\PortalIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    public function test_protected_pages_require_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/officer/reviews')->assertRedirect('/login');
    }

    public function test_officer_email_link_is_remembered_when_login_is_required(): void
    {
        $this->get('/officer/reviews/321')
            ->assertRedirect('/login')
            ->assertSessionHas('url.intended', 'http://localhost/officer/reviews/321');
    }

    public function test_officer_returns_to_the_email_request_after_login(): void
    {
        $actor = [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'officer',
            'name' => 'เจ้าหน้าที่ทดสอบ',
            'email' => 'officer@g.swu.ac.th',
            'label' => 'เจ้าหน้าที่',
            'role' => 'officer',
        ];
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('officer', 'secret')->andReturn($actor);
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->withSession(['url.intended' => 'http://localhost/officer/reviews/321'])
            ->post('/login', [
                'buasri_id' => 'officer',
                'password' => 'secret',
            ])
            ->assertRedirect('http://localhost/officer/reviews/321');

        $this->assertSame('officer', session('portal.actor.role'));
        $this->assertNull(session('url.intended'));
    }

    public function test_removed_approver_page_is_not_available(): void
    {
        $this->get('/approver/reviews')->assertNotFound();
    }

    #[DataProvider('staffHomeRoutes')]
    public function test_root_redirects_staff_to_their_home_page(string $role, string $destination): void
    {
        $this->withSession(['portal.actor' => [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => $role === 'officer' ? 'เจ้าหน้าที่' : 'ผู้อนุมัติ',
            'role' => $role,
        ]])->get('/')->assertRedirect($destination);
    }

    public static function staffHomeRoutes(): array
    {
        return [
            'officer' => ['officer', '/officer/reviews'],
        ];
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
            'label' => 'บุคลากร',
            'role' => 'user',
            'available_roles' => ['user', 'officer'],
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

    public function test_development_role_switcher_can_use_requester_role_with_the_same_account(): void
    {
        config()->set('course-workflow.dev_role_switcher', true);
        $actor = [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'เจ้าหน้าที่',
            'role' => 'officer',
            'available_roles' => ['user', 'officer'],
        ];
        $identity = Mockery::mock(PortalIdentityService::class);
        $identity->shouldReceive('authenticate')->once()->with('someone', 'secret')->andReturn($actor);
        $this->app->instance(PortalIdentityService::class, $identity);

        $this->post('/login', [
            'buasri_id' => 'someone',
            'password' => 'secret',
            'login_role' => 'user',
        ])->assertRedirect('/');

        $this->assertSame('user', session('portal.actor.role'));
        $this->assertSame('ผู้ใช้งาน', session('portal.actor.label'));
        $this->assertArrayNotHasKey('available_roles', session('portal.actor'));
    }

    public function test_development_role_switcher_keeps_each_tabs_role_separate(): void
    {
        config()->set('course-workflow.dev_role_switcher', true);
        Route::middleware('portal.role:user')->get('/_test/user-tab', function (Request $request) {
            return response()->json(['role' => $request->attributes->get('portal.actor')['role']]);
        });
        Route::middleware('portal.role:user')->get('/_test/user-tab-redirect', function () {
            return redirect('/_test/destination');
        });

        $session = [
            'portal.actor' => [
                'pers_id' => 100,
                'buasri_id' => 'someone',
                'name' => 'ผู้ใช้งานทดสอบ',
                'label' => 'เจ้าหน้าที่',
                'role' => 'officer',
            ],
            'portal.available_roles' => ['user', 'officer'],
        ];

        $this->withSession($session)
            ->get('/_test/user-tab?_portal_role=user')
            ->assertOk()
            ->assertJson(['role' => 'user']);

        $this->withSession($session)
            ->get('/_test/user-tab-redirect?_portal_role=user')
            ->assertRedirect('/_test/destination?_portal_role=user');
    }

    public function test_logged_in_account_can_open_login_in_another_tab_during_development(): void
    {
        config()->set('course-workflow.dev_role_switcher', true);

        $this->withSession(['portal.actor' => [
            'pers_id' => 100,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'label' => 'ผู้ใช้งาน',
            'role' => 'user',
        ]])->get('/login')
            ->assertOk()
            ->assertSee('บทบาทสำหรับทดสอบ');
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
            'login_role' => 'officer',
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
