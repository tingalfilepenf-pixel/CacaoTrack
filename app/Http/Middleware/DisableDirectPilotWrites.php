<?php

namespace App\Http\Middleware;

use App\Support\SubmissionWorkflowSwitch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the submission workflow is ON, the direct Mortality and Stock Out
 * official-write routes are disabled together so no legacy-key holder can
 * bypass verification.
 */
class DisableDirectPilotWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SubmissionWorkflowSwitch::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
