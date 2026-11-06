<?php

namespace App\Http\Requests;

use App\Models\Batch;

class UpdateBatchRequest extends AppFormRequest
{
    protected $errorBag = 'batch';

    /**
     * Once a batch has operational records (mortality/regraft/stock out),
     * its total grafted count is locked (RULE 6). The rule is removed so the
     * value cannot be changed even if a client submits it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $batch = $this->route('batch');

        $rules = Batch::rules($batch?->batch_id);

        if ($batch instanceof Batch && $batch->hasOperationalRecords()) {
            unset($rules['total_grafted']);
        }

        if ($batch instanceof Batch && $batch->hasTrackedStockUse()) {
            // The approved own-nursery batch's quantity, variety and code are
            // tied to the stock it consumed. Status and grafted date stay
            // editable as before.
            unset($rules['total_grafted'], $rules['scion_variety_id'], $rules['batch_code']);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_code.required' => 'The batch code is required.',
            'batch_code.unique' => 'This batch code is already in use.',
            'total_grafted.required' => 'The total grafted count is required.',
            'grafted_date.before_or_equal' => 'The grafted date cannot be in the future.',
        ];
    }
}
