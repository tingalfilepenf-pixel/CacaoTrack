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
];
