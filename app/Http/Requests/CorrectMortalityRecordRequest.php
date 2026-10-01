<?php

namespace App\Http\Requests;

use App\Actions\CorrectRecord;
use App\Models\MortalityRecord;
use Illuminate\Validation\Rule;

/**
 * Correction of an existing Mortality_Record (`record_corrected`, OD-5).
 *
 * The batch is intentionally absent: a correction edits the recorded values in
 * place and never moves mortality to another batch. The rules match
 * {@see StoreMortalityRecordRequest} so an edit is validated exactly like a new
 * entry; the live mortality-capacity check is re-run inside
 * {@see CorrectRecord::mortality()}.
 */
class CorrectMortalityRecordRequest extends AppFormRequest
{
    protected $errorBag = 'mortality_correction';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
