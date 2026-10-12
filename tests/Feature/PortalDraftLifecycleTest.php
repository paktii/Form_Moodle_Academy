<?php

namespace Tests\Feature;

use App\Http\Middleware\DiscardPortalDraftWhenLeavingForm;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalDraftLifecycleTest extends TestCase
{
    public function test_opening_create_entry_point_discards_existing_draft_and_pending_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('course-requests/draft.pdf', 'draft');

        $response = $this->withSession([
            'portal.actor' => $this->requester(),
            'portal.draft' => [
                'project_name' => 'ข้อมูลเดิม',
                'additional_files' => [[
                    'additional_path' => 'course-requests/draft.pdf',
                    'additional_pending' => true,
                ]],
            ],
            'portal.completed.1' => true,
        ])->get('/request/create');

        $response->assertRedirect('/request/create/1');
        $response->assertSessionMissing('portal.draft');
        $response->assertSessionMissing('portal.completed');
        Storage::disk('local')->assertMissing('course-requests/draft.pdf');
    }

    public function test_form_routes_preserve_draft_and_disable_browser_cache(): void
    {
        $session = $this->app['session']->driver();
        $session->put('portal.actor', $this->requester());
        $session->put('portal.draft.project_name', 'ข้อมูลที่กำลังกรอก');

        $request = Request::create('/request/create/2', 'GET');
        $request->setLaravelSession($session);
        $route = new Route(['GET'], '/request/create/{step}', fn () => null);
        $route->name('requests.form');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = (new DiscardPortalDraftWhenLeavingForm)->handle(
            $request,
            fn () => response('form'),
        );

        $this->assertSame('ข้อมูลที่กำลังกรอก', $session->get('portal.draft.project_name'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function requester(): array
    {
        return [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'someone',
            'name' => 'ผู้ใช้งานทดสอบ',
            'email' => 'someone@g.swu.ac.th',
            'label' => 'บุคลากร',
            'role' => 'user',
        ];
    }
}
