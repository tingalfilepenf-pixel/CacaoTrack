<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The operational-submission cutover instant (RULE 6).
 *
 * The cutover is an absolute moment: 2026-12-01 00:00:00 Asia/Manila. The
 * moment is parsed in its own timezone, so comparisons never depend on
 * `config('app.timezone')` (which remains UTC). Nothing consumes this helper
 * yet.
 */
final class OperationalCutover
{
    public static function moment(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            (string) config('cacaotrack.operational_cutover.at'),
            (string) config('cacaotrack.operational_cutover.timezone'),
        );
    }

    public static function hasOccurred(?CarbonInterface $now = null): bool
    {
        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo(self::moment());
    }
}
