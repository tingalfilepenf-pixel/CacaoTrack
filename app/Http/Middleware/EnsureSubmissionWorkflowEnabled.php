<?php

namespace App\Http\Middleware;

use App\Support\SubmissionWorkflowSwitch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate every Phase 3 submission route behind the explicit workflow switch.
 *
 * When the switch is OFF the routes are treated as non-existent so the live
 * app keeps its current direct-write behaviour and does not advertise the
 * proposed workflow.
 */
class EnsureSubmissionWorkflowEnabled
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $enabled = $module === null
            ? SubmissionWorkflowSwitch::enabled()
            : SubmissionWorkflowSwitch::moduleEnabled($module);

        if (! $enabled) {
            abort(404);
        }

        return $next($request);
    }
}
