<?php

namespace App\Http\Requests;

use App\Models\Batch;

/**
 * Mortality submission capture. Reuses the direct Mortality request shape and
 * messages, but applies the stricter ELIG-01 batch rule (active + not
 * archived) required for submissions.
 */
class StoreMortalitySubmissionRequest extends StoreMortalityRecordRequest
{
    protected $errorBag = 'submission_mortality';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['batch_id'] = ['required', 'integer', Batch::submissionEligibleExistsRule('batch_id')];

        return $rules;
    }
}
