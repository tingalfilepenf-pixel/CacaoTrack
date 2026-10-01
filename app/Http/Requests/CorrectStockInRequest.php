<?php

namespace App\Http\Requests;

use App\Models\ScionVariety;
use App\Models\Supplier;

/**
 * Correction of an existing Stock_In receiving record (`record_corrected`,
 * OD-5). The rules mirror {@see StoreStockInRequest} so a corrected receiving
 * is validated exactly like a new one (including the scion/seedling "both or
 * neither" pairing, which uses built-in rules and no discriminator column).
 */
class CorrectStockInRequest extends AppFormRequest
{
    protected $errorBag = 'stockin_correction';

    /**
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
            'quantity.required' => 'The received quantity is required.',
            'quantity.min' => 'The received quantity must be at least 1.',
            'scion_variety_id.exists' => 'The selected scion variety is not available.',
            'scion_variety_id.required_with' => 'Select a scion variety and enter a unit price together, or leave both blank for a seedling-only stock-in.',
            'unit_price.required_with' => 'Select a scion variety and enter a unit price together, or leave both blank for a seedling-only stock-in.',
            'unit_price.numeric' => 'Unit price must be a number.',
            'unit_price.min' => 'Unit price cannot be negative.',
            'origin.required' => 'The origin is required.',
            'date_received.required' => 'The date received is required.',
            'date_received.before_or_equal' => 'The date received cannot be in the future.',
        ];
    }
}
