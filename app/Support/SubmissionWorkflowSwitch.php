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
    /**
     * The master switch. Every module is additionally gated by this switch.
     */
    public static function enabled(): bool
    {
        return (bool) config('cacaotrack.operational_submission.enabled', false);
    }

    /**
     * Whether one module is active. The master switch always wins.
     */
    public static function moduleEnabled(string $module): bool
    {
        return self::enabled()
            && (bool) config("cacaotrack.operational_submission.modules.{$module}", false);
    }

    /**
     * The independent Stock In module switch, ANDed with the master switch.
     */
    public static function stockInEnabled(): bool
    {
        return self::moduleEnabled('stock_in');
    }

    /**
     * The independent Batch Creation module switch, ANDed with the master
     * switch.
     */
    public static function batchCreationEnabled(): bool
    {
        return self::moduleEnabled('batch_creation');
    }
}
