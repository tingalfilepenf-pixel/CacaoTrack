<?php

namespace App\Http\Requests;

/**
 * Stock In submission capture. Reuses the direct Stock In request shape,
 * normalization, messages and duplicate-fingerprint payload unchanged; only
 * the error bag differs so the submission page never collides with the direct
 * form.
 */
class StoreStockInSubmissionRequest extends StoreStockInRequest
{
    protected $errorBag = 'submission_stockin';
}
