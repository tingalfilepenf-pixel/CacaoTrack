<?php

namespace App\Http\Requests;

use App\Models\Batch;

class UpdateBatchRequest extends AppFormRequest
{
    protected $errorBag = 'batch';

    /**
     * Once a batch has operational records (mortality/replanting/stock out),
     * its total grafted and viable counts are locked (RULE 6). The rules are
     * removed so the values cannot be changed even if a client submits them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $batch = $this->route('batch');

        $rules = Batch::rules($batch?->batch_id);

        if ($batch instanceof Batch && $batch->hasOperationalRecords()) {
            unset($rules['total_grafted'], $rules['viable_count']);
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
            'viable_count.lte' => 'The viable count cannot exceed the total grafted count.',
            'grafted_date.before_or_equal' => 'The grafted date cannot be in the future.',
        ];
    }
}
