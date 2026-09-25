<?php

namespace App\Http\Requests;

use App\Models\Supplier;

class StoreSupplierRequest extends AppFormRequest
{
    protected $errorBag = 'supplier';

    /**
     * Inline supplier quick-add used by the Stock In page (FR-11).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Supplier::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_name.required' => 'The supplier name is required.',
            'contact_number.required' => 'The supplier contact number is required.',
            'address.required' => 'The supplier address is required.',
        ];
    }
}
