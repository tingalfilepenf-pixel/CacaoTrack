<?php

namespace App\Http\Requests;

use App\Models\Batch;

class StoreInventorySnapshotRequest extends AppFormRequest
{
    protected $errorBag = 'inventory';

    /**
     * "Record Monthly Inventory" only accepts the batch and the snapshot month.
     * Every count is derived server-side from live operational data (Batch,
     * Mortality, Replanting) — never from client input (RULE 5, FR-08).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'inventory_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_id.required' => 'Please select a batch to record.',
            'inventory_date.before_or_equal' => 'The inventory date cannot be in the future.',
        ];
    }
}
