<?php

namespace App\Http\Requests;

use App\Models\Client;

class StoreClientRequest extends AppFormRequest
{
    protected $errorBag = 'client';

    /**
     * Inline client quick-add used by the Stock Out page (FR-12).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Client::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_name.required' => 'The client name is required.',
            'contact_number.required' => 'The client contact number is required.',
            'address.required' => 'The client address is required.',
        ];
    }
}
