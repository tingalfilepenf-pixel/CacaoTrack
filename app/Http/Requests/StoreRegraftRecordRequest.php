<?php

namespace App\Http\Requests;

use App\Actions\RegraftBatch;
use App\Models\Batch;
use App\Models\ScionVariety;

class StoreRegraftRecordRequest extends AppFormRequest
{
    protected $errorBag = 'regraft';

    /**
     * Request-shape validation for a regraft. Live-state checks (the regraftable
     * cap, the source's grafted date, the scion variety) are re-checked inside
     * the locked transaction by {@see RegraftBatch}.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'scion_variety_id' => ['required', 'integer', ScionVariety::activeExistsRule('scion_variety_id')],
            'regraft_date' => ['required', 'date', 'before_or_equal:today'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_id.required' => 'Please select a source batch.',
            'batch_id.exists' => 'The selected source batch is not available.',
            'quantity.required' => 'The regraft quantity is required.',
            'quantity.integer' => 'The regraft quantity must be a whole number.',
            'quantity.min' => 'The regraft quantity must be at least 1.',
            'scion_variety_id.required' => 'Please select the scion variety for the new sub-batch.',
            'scion_variety_id.exists' => 'The selected scion variety is not available.',
            'regraft_date.required' => 'The regraft date is required.',
            'regraft_date.date' => 'The regraft date must be a valid date.',
            'regraft_date.before_or_equal' => 'The regraft date cannot be in the future.',
        ];
    }
}
