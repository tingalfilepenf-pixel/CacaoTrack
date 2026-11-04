<?php

namespace App\Http\Requests;

use App\Models\Batch;

/**
 * Stock Out submission capture. Reuses the direct Stock Out request shape and
 * messages, but applies the stricter ELIG-01 batch rule (active + not
 * archived) to every detail line.
 */
class StoreStockOutSubmissionRequest extends StoreStockOutRequest
{
    protected $errorBag = 'submission_stockout';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['details.*.batch_id'] = ['required', 'integer', Batch::submissionEligibleExistsRule('batch_id')];

        return $rules;
    }
}
