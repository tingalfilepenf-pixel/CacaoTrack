<?php

namespace App\Http\Requests;

use App\Models\Supplier;

class StoreStockInRequest extends AppFormRequest
{
    protected $errorBag = 'stockin';

    /**
     * Stock In has no `batch_id` (ERD §3.7): the five-day viability count
     * happens before a batch is established. `viable <= quantity` is enforced
     * here as the user-facing validation layer on top of the model guard.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'viable' => ['required', 'integer', 'min:0', 'lte:quantity'],
            'origin' => ['required', 'string', 'max:255'],
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
            'quantity.required' => 'The ordered seedling quantity is required.',
            'quantity.min' => 'The ordered quantity must be at least 1.',
            'viable.required' => 'The viable count after the five-day recount is required.',
            'viable.min' => 'The viable count cannot be negative.',
            'viable.lte' => 'Viable count cannot exceed the ordered quantity.',
            'origin.required' => 'The seedling origin is required.',
            'date_received.required' => 'The date received is required.',
            'date_received.before_or_equal' => 'The date received cannot be in the future.',
        ];
    }
}
