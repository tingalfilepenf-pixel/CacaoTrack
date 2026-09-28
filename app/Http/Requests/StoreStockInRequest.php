<?php

namespace App\Http\Requests;

use App\Models\ScionVariety;
use App\Models\Supplier;

class StoreStockInRequest extends AppFormRequest
{
    protected $errorBag = 'stockin';

    /**
     * Stock In has no `batch_id` (ERD §3.7): the received `quantity` is
     * recorded immediately, with no separate seedling viability count.
     *
     * `scion_variety_id` and `unit_price` are a matched pair (client-confirmed
     * merge of Scion_Receiving into Stock_In): both present for a scion-
     * inclusive row, both absent for a pure-seedling row. `required_with` each
     * way expresses "both or neither" using built-in rules — there is no
     * discriminator column, the scion variety id is the signal.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'scion_variety_id' => ['nullable', 'integer', ScionVariety::activeExistsRule('scion_variety_id'), 'required_with:unit_price'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'required_with:scion_variety_id'],
            'origin' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Please select a supplier.',
            'quantity.required' => 'The received seedling quantity is required.',
            'quantity.min' => 'The received quantity must be at least 1.',
            'scion_variety_id.exists' => 'The selected scion variety is not available.',
            'scion_variety_id.required_with' => 'Select a scion variety and enter a unit price together, or leave both blank for a seedling-only stock-in.',
            'unit_price.required_with' => 'Select a scion variety and enter a unit price together, or leave both blank for a seedling-only stock-in.',
            'unit_price.numeric' => 'Unit price must be a number.',
            'unit_price.min' => 'Unit price cannot be negative.',
            'origin.required' => 'The seedling origin is required.',
            'notes.max' => 'The notes are too long.',
            'date_received.required' => 'The date received is required.',
            'date_received.before_or_equal' => 'The date received cannot be in the future.',
        ];
    }
}
