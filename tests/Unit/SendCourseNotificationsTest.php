<?php

namespace Tests\Unit;

use App\Console\Commands\SendCourseNotifications;
use App\Models\CourseRequest;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\URL;
use ReflectionMethod;
use Tests\TestCase;

class SendCourseNotificationsTest extends TestCase
{
    public function test_retry_delay_increases_exponentially_and_stops_at_the_configured_maximum(): void
    {
        config()->set('course-workflow.notifications.retry_delay_seconds', 30);
        config()->set('course-workflow.notifications.max_retry_delay_seconds', 100);

        $method = new ReflectionMethod(SendCourseNotifications::class, 'retryDelaySeconds');
        $command = new SendCourseNotifications;

        $this->assertSame(30, $method->invoke($command, 1));
        $this->assertSame(60, $method->invoke($command, 2));
        $this->assertSame(100, $method->invoke($command, 3));
        $this->assertSame(100, $method->invoke($command, 5));
    }

    public function test_retry_delay_never_uses_a_maximum_below_the_base_delay(): void
    {
        config()->set('course-workflow.notifications.retry_delay_seconds', 45);
        config()->set('course-workflow.notifications.max_retry_delay_seconds', 10);

        $method = new ReflectionMethod(SendCourseNotifications::class, 'retryDelaySeconds');

        $this->assertSame(45, $method->invoke(new SendCourseNotifications, 1));
    }

    public function test_local_and_uat_email_is_redirected_to_the_configured_test_recipient(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);
        $notification = new NotificationOutbox;
        $notification->notification_type = 'DEAN_REVIEW_REQUIRED';
        $notification->recipient_email = 'real-recipient@g.swu.ac.th';
        $request = new CourseRequest(['requester_pers_id' => 713796]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame(
                'pakteema@g.swu.ac.th',
                $method->invoke(new SendCourseNotifications, $notification, $request),
            );
        }
    }

    public function test_local_and_uat_email_follows_the_requesting_tester(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);
        $notification = new NotificationOutbox;
        $notification->notification_type = 'DEAN_REVIEW_REQUIRED';
        $notification->recipient_email = 'real-recipient@g.swu.ac.th';
        $request = new CourseRequest(['requester_pers_id' => 701341]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame(
                'suwimon@g.swu.ac.th',
                $method->invoke(new SendCourseNotifications, $notification, $request),
            );
        }
    }

    public function test_local_and_uat_requester_email_is_redirected_to_the_configured_test_recipient(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');
        $request = new CourseRequest(['requester_pers_id' => 713796]);
        $requesterNotificationTypes = [
            'DEAN_REJECTED',
            'REQUEST_REJECTED',
            'COURSE_ID_RECORDED',
            'STUDENT_ROSTER_ACKNOWLEDGED',
            'STUDENT_ROSTER_REOPENED',
        ];

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            foreach ($requesterNotificationTypes as $notificationType) {
                $notification = new NotificationOutbox;
                $notification->notification_type = $notificationType;
                $notification->recipient_email = 'requester@g.swu.ac.th';

                $this->assertSame(
                    'pakteema@g.swu.ac.th',
                    $method->invoke(new SendCourseNotifications, $notification, $request),
                    "Failed asserting test recipient for {$notificationType} in {$environment}.",
                );
            }
        }
    }

    public function test_local_and_uat_form_head_email_uses_an_admin_assigned_test_recipient(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);
        $notification = new NotificationOutbox;
        $notification->notification_type_code = 'REQUEST_COMPLETED_FORM_HEAD';
        $notification->recipient_pers_id = 701341;
        $notification->recipient_email = 'directory-email@g.swu.ac.th';
        $request = new CourseRequest(['requester_pers_id' => 713796]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame(
                'suwimon@g.swu.ac.th',
                $method->invoke(new SendCourseNotifications, $notification, $request),
            );
        }
    }

    public function test_local_and_uat_real_form_head_email_is_redirected_to_the_requesting_tester(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        config()->set('course-workflow.notifications.test_recipients', [
            713796 => 'pakteema@g.swu.ac.th',
            701341 => 'suwimon@g.swu.ac.th',
        ]);
        $notification = new NotificationOutbox;
        $notification->notification_type_code = 'REQUEST_COMPLETED_FORM_HEAD';
        $notification->recipient_pers_id = 999999;
        $notification->recipient_email = 'real-head@g.swu.ac.th';
        $request = new CourseRequest(['requester_pers_id' => 713796]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        foreach (['local', 'uat'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame(
                'pakteema@g.swu.ac.th',
                $method->invoke(new SendCourseNotifications, $notification, $request),
            );
        }
    }

    public function test_production_email_uses_the_actual_recipient(): void
    {
        config()->set('course-workflow.notifications.test_recipient_email', 'pakteema@g.swu.ac.th');
        $this->app->detectEnvironment(fn () => 'production');
        $notification = new NotificationOutbox;
        $notification->notification_type = 'DEAN_REVIEW_REQUIRED';
        $notification->recipient_email = 'real-recipient@g.swu.ac.th';
        $request = new CourseRequest(['requester_pers_id' => 701341]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        $this->assertSame(
            'real-recipient@g.swu.ac.th',
            $method->invoke(new SendCourseNotifications, $notification, $request),
        );
    }

    public function test_notification_sender_rejects_a_recipient_outside_google_workspace(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $notification = new NotificationOutbox;
        $notification->notification_type = 'DEAN_REVIEW_REQUIRED';
        $notification->recipient_email = 'real-recipient@example.com';
        $request = new CourseRequest(['requester_pers_id' => 701341]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'recipientEmailFor');

        $this->expectException(\RuntimeException::class);
        $method->invoke(new SendCourseNotifications, $notification, $request);
    }

    public function test_dean_email_uses_one_signed_detail_link_without_a_preselected_decision(): void
    {
        $url = URL::temporarySignedRoute(
            'dean.review.confirm',
            now()->addMonth(),
            ['id' => 123, 'notification' => 456],
        );

        $this->assertStringContainsString('/dean/reviews/123?', $url);
        $this->assertStringContainsString('notification=456', $url);
        $this->assertStringNotContainsString('/approve', $url);
        $this->assertStringNotContainsString('/reject', $url);
    }

    public function test_acting_dean_email_addresses_the_recipient_as_acting_for_the_official_position(): void
    {
        $request = new CourseRequest([
            'dean_position_name' => 'คณบดีคณะแพทยศาสตร์',
            'dean_is_acting' => true,
        ]);
        $method = new ReflectionMethod(SendCourseNotifications::class, 'deanGreeting');

        $this->assertSame(
            'ผู้รักษาการแทน คณบดีคณะแพทยศาสตร์',
            $method->invoke(new SendCourseNotifications, $request),
        );
    }
}
