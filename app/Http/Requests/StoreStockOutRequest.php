<?php

namespace App\Http\Requests;

use App\Models\Batch;
use App\Models\Client;
use App\Models\StockOutDetail;
use Illuminate\Validation\Rule;

class StoreStockOutRequest extends AppFormRequest
{
    protected $errorBag = 'stockout';

    /**
     * A Stock Out is a header plus one `details[]` line per batch/scion
     * variety (ERD §3.14/§3.15). `scion_variety_id` is intentionally absent:
     * it is derived server-side from the line's batch (locked business rule 4).
     * `amount` and `total_amount` are also absent: both are computed
     * server-side from quantity and unit price.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Client::activeExistsRule('client_id')],
            'release_date' => ['required', 'date', 'before_or_equal:today'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit' => ['required', 'string', Rule::in(StockOutDetail::UNITS)],
            'details.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $units = implode(', ', StockOutDetail::UNITS);

        return [
            'client_id.required' => 'Please select the client the cacao is delivered to.',
            'release_date.required' => 'The release date is required.',
            'release_date.before_or_equal' => 'The release date cannot be in the future.',
            'details.required' => 'At least one stock-out line is required.',
            'details.min' => 'At least one stock-out line is required.',
            'details.*.batch_id.required' => 'Please select a batch for every line.',
            'details.*.quantity.required' => 'A quantity is required for every line.',
            'details.*.quantity.min' => 'Every line quantity must be at least 1.',
            'details.*.unit.required' => 'A unit is required for every line.',
            'details.*.unit.in' => "Every unit must be one of: {$units}.",
            'details.*.unit_price.numeric' => 'The unit price must be a number.',
            'details.*.unit_price.min' => 'The unit price cannot be negative.',
        ];
    }
}
