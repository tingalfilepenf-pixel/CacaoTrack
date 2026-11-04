<?php

namespace App\Support;

/**
 * The explicit Phase 3 submission-workflow switch (RULE 6).
 *
 * This is intentionally a boolean setting, not a date check. The workflow is
 * OFF by default so the live application keeps writing official records
 * directly until the owner turns it on.
 */
final class SubmissionWorkflowSwitch
{
    public static function enabled(): bool
    {
        return (bool) config('cacaotrack.operational_submission.enabled', false);
    }
}
