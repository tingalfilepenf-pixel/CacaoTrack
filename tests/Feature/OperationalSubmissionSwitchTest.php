<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubmissionWorkflowEnabled;
use App\Support\OperationalCutover;
use App\Support\SubmissionWorkflowSwitch;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class OperationalSubmissionSwitchTest extends TestCase
{
    public function test_the_switch_is_off_by_default(): void
    {
        $this->assertFalse(config('cacaotrack.operational_submission.enabled'));
        $this->assertFalse(SubmissionWorkflowSwitch::enabled());
    }

    public function test_the_switch_can_be_enabled_explicitly(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);

        $this->assertTrue(SubmissionWorkflowSwitch::enabled());
    }

    public function test_the_switch_is_not_the_date_based_cutover_helper(): void
    {
        config(['cacaotrack.operational_submission.enabled' => false]);

        // The cutover may already have occurred by date, but the workflow must
        // still be OFF until the owner explicitly turns the boolean switch on.
        $this->assertFalse(SubmissionWorkflowSwitch::enabled());
        $this->assertNotSame(OperationalCutover::class, SubmissionWorkflowSwitch::class);
    }

    public function test_the_middleware_returns_404_while_disabled(): void
    {
        config(['cacaotrack.operational_submission.enabled' => false]);

        $this->expectException(NotFoundHttpException::class);

        (new EnsureSubmissionWorkflowEnabled)->handle(
            Request::create('/operational/submissions', 'GET'),
            fn () => response('enabled'),
        );
    }

    public function test_the_middleware_passes_while_enabled(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);

        $response = (new EnsureSubmissionWorkflowEnabled)->handle(
            Request::create('/operational/submissions', 'GET'),
            fn () => response('enabled'),
        );

        $this->assertSame('enabled', $response->getContent());
    }
}
