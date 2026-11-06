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
    | One explicit master switch for the approval workflow, plus one explicit
    | per-module switch for each module that may be turned on independently.
    | Everything is OFF by default and deliberately not a date check: the live
    | app keeps writing directly until the owner enables it.
    |
    */

    'operational_submission' => [
        'enabled' => env('CACAOTRACK_OPERATIONAL_SUBMISSION_ENABLED', false),

        'batch_code_lock_timeout' => env('CACAOTRACK_BATCH_CODE_LOCK_TIMEOUT', 10),

        'modules' => [
            'stock_in' => env('CACAOTRACK_STOCK_IN_SUBMISSION_ENABLED', false),
            'batch_creation' => env('CACAOTRACK_BATCH_SUBMISSION_ENABLED', false),
        ],
    ],
];
