<?php

namespace App\Http\Middleware;

use App\Support\SubmissionWorkflowSwitch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a submission module is ON, its direct official-write route is disabled
 * so no legacy-key holder can bypass verification. Without a module argument
 * this keeps the original Phase 3 master-switch behaviour for Mortality and
 * Stock Out.
 */
class DisableDirectPilotWrites
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $enabled = $module === null
            ? SubmissionWorkflowSwitch::enabled()
            : SubmissionWorkflowSwitch::moduleEnabled($module);

        if ($enabled) {
            abort(404);
        }

        return $next($request);
    }
}
