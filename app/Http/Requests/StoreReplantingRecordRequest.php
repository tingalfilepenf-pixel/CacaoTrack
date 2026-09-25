<?php

namespace App\Http\Requests;

use App\Models\Batch;

class StoreReplantingRecordRequest extends AppFormRequest
{
    protected $errorBag = 'replanting';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'replanting_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_id.required' => 'Please select a batch.',
            'quantity.required' => 'The replanting quantity is required.',
            'quantity.min' => 'The replanting quantity must be at least 1.',
            'replanting_date.required' => 'The replanting date is required.',
            'replanting_date.date' => 'The replanting date must be a valid date.',
        ];
    }
}
