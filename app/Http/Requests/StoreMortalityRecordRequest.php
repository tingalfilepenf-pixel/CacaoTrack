<?php

namespace App\Http\Requests;

use App\Models\Batch;
use App\Models\MortalityRecord;
use Illuminate\Validation\Rule;

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
            'cause' => ['required', 'string', Rule::in(MortalityRecord::CAUSES)],
            'cause_other' => [
                'nullable',
                'string',
                'max:200',
                Rule::requiredIf(fn () => $this->input('cause') === MortalityRecord::CAUSE_OTHERS),
            ],
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
            'cause.in' => 'Please select a valid cause of mortality.',
            'cause_other.required' => 'Please describe the cause of mortality when "Others" is selected.',
            'cause_other.max' => 'The cause description may not be greater than 200 characters.',
            'recorded_at.required' => 'The date recorded is required.',
            'recorded_at.date' => 'The date recorded must be a valid date.',
        ];
    }
}
