<?php

namespace App\Http\Requests;

use App\Models\Batch;

class StoreBatchRequest extends AppFormRequest
{
    protected $errorBag = 'batch';

    /**
     * Batch Code and Grafted Date are system-assigned (next free code /
     * today) — see {@see Batch::storeRules()}.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Batch::storeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_grafted.required' => 'The total grafted count is required.',
            'total_grafted.min' => 'The total grafted count must be at least 1.',
        ];
    }
}
