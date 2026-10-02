<?php

namespace Tests\Unit;

use App\Console\Commands\SendCourseNotifications;
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
}
