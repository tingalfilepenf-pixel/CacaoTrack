<?php

namespace App\Http\Requests;

use App\Models\Batch;

class StoreBatchRequest extends AppFormRequest
{
    protected $errorBag = 'batch';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Batch::rules();
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
            'total_grafted.min' => 'The total grafted count must be at least 1.',
            'viable_count.required' => 'The viable count is required.',
            'viable_count.lte' => 'The viable count cannot exceed the total grafted count.',
            'grafted_date.required' => 'The grafted date is required.',
            'grafted_date.before_or_equal' => 'The grafted date cannot be in the future.',
        ];
    }
}
