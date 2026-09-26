<?php

namespace App\Http\Requests;

use App\Models\ScionReceiving;

class StoreScionReceivingRequest extends AppFormRequest
{
    protected $errorBag = 'scion_receiving';

    /**
     * Scion Receiving is a standalone receiving log (ERD §3.16): it holds no
     * stock balance. The supplier is optional because scions are not always
     * tied to a formal Supplier record. Rules are centralized in
     * `ScionReceiving::rules()` (RULE 6); no freshness/viability rule is
     * applied yet (deliberately deferred pending client confirmation).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ScionReceiving::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scion_variety_id.required' => 'Please select a scion variety.',
            'scion_variety_id.exists' => 'The selected scion variety is not available.',
            'supplier_id.exists' => 'The selected supplier is not available.',
            'quantity.required' => 'The quantity received is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be greater than zero.',
            'unit_price.numeric' => 'Unit price must be a number.',
            'unit_price.min' => 'Unit price cannot be negative.',
            'date_received.required' => 'The date received is required.',
            'date_received.date' => 'The date received must be a valid date.',
            'date_received.before_or_equal' => 'The date received cannot be in the future.',
            'origin.max' => 'The origin is too long.',
            'notes.max' => 'The notes are too long.',
        ];
    }
}
