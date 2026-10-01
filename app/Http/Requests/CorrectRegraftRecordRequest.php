<?php

namespace App\Http\Requests;

use App\Actions\CorrectRecord;
use App\Models\ScionVariety;

/**
 * Correction of an existing Regraft_Record (`record_corrected`, OD-5).
 *
 * The source batch is absent: a correction edits the regraft it created. The
 * live-state checks (the surviving cap on the source, the source's grafted
 * date, the created sub-batch's dependent activity) are re-run inside
 * {@see CorrectRecord::regraft()}.
 */
class CorrectRegraftRecordRequest extends AppFormRequest
{
    protected $errorBag = 'regraft_correction';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
            'quantity.required' => 'The regraft quantity is required.',
            'quantity.integer' => 'The regraft quantity must be a whole number.',
            'quantity.min' => 'The regraft quantity must be at least 1.',
            'scion_variety_id.required' => 'Please select the scion variety for the sub-batch.',
            'scion_variety_id.exists' => 'The selected scion variety is not available.',
            'regraft_date.required' => 'The regraft date is required.',
            'regraft_date.date' => 'The regraft date must be a valid date.',
            'regraft_date.before_or_equal' => 'The regraft date cannot be in the future.',
        ];
    }
}
