<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operational cutover
    |--------------------------------------------------------------------------
    |
    | The explicit instant when the operational-submission workflow takes
    | effect. It is stored with its own timezone and must be compared as an
    | absolute moment; it is deliberately independent of `app.timezone`, which
    | stays UTC.
    |
    */

    'operational_cutover' => [
        'at' => '2026-12-01 00:00:00',
        'timezone' => 'Asia/Manila',
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational submission workflow switch
    |--------------------------------------------------------------------------
    |
    | One explicit boolean switch for the Phase 3 Mortality / Stock Out
    | approval workflow. It is OFF by default and is deliberately not a date
    | check: the live app keeps writing directly until the owner enables it.
    |
    */

    'operational_submission' => [
        'enabled' => env('CACAOTRACK_OPERATIONAL_SUBMISSION_ENABLED', false),
    ],
];
