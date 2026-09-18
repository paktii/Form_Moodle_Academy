<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'course-workflow.actors.user.password' => 'user-secret',
            'course-workflow.actors.officer.password' => 'officer-secret',
            'course-workflow.actors.approver.password' => 'approver-secret',
        ]);
    }

    public function test_protected_pages_require_login(): void
    {
        $this->get('/')->assertRedirect('/login/user');
        $this->get('/officer/reviews')->assertRedirect('/login/officer');
        $this->get('/approver/reviews')->assertRedirect('/login/approver');
    }

    public function test_each_configured_role_can_log_in(): void
    {
        foreach ([
            'user' => ['/', 'requester', 'user-secret'],
            'officer' => ['/officer/reviews', 'officer', 'officer-secret'],
            'approver' => ['/approver/reviews', 'approver', 'approver-secret'],
        ] as $role => [$destination, $buasriId, $password]) {
            $response = $this->post("/login/$role", [
                'buasri_id' => $buasriId,
                'password' => $password,
            ]);

            $response->assertRedirect($destination);
            $this->assertSame($role, session('portal.actor.role'));
            $this->post('/logout');
        }
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->post('/login/user', [
            'buasri_id' => 'requester',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('buasri_id');

        $this->assertNull(session('portal.actor'));
    }

    public function test_role_cannot_open_another_roles_pages(): void
    {
        $this->withSession(['portal.actor' => [
            ...config('course-workflow.actors.user'),
            'role' => 'user',
        ]])->get('/officer/reviews')->assertForbidden();
    }

    public function test_unknown_login_role_is_not_found(): void
    {
        $this->get('/login/invalid')->assertNotFound();
    }
}
