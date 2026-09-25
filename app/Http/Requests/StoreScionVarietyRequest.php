<?php

namespace App\Http\Requests;

use App\Models\ScionVariety;

class StoreScionVarietyRequest extends AppFormRequest
{
    protected $errorBag = 'scion_variety';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ScionVariety::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scion_variety_name.required' => 'The scion variety name is required.',
            'scion_variety_name.max' => 'The scion variety name is too long.',
        ];
    }
}
