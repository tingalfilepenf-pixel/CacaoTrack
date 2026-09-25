<?php

namespace App\Http\Requests;

use App\Models\Batch;

class StoreMortalityRecordRequest extends AppFormRequest
{
    protected $errorBag = 'mortality';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'cause' => ['required', 'string', 'max:255'],
            'recorded_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_id.required' => 'Please select a batch.',
            'quantity.required' => 'The mortality quantity is required.',
            'quantity.min' => 'The mortality quantity must be at least 1.',
            'cause.required' => 'The cause of mortality is required.',
            'recorded_at.required' => 'The date recorded is required.',
            'recorded_at.date' => 'The date recorded must be a valid date.',
        ];
    }
}
