<?php

namespace App\Http\Requests;

use App\Actions\CorrectRecord;
use App\Models\Batch;
use App\Models\Client;
use App\Models\StockOutDetail;
use Illuminate\Validation\Rule;

/**
 * Correction of an existing Stock_Out header and its detail lines
 * (`record_corrected`, OD-5).
 *
 * Lines are edited in place and identified by `id`; they are never added or
 * removed by a correction. The same request-shape rules as
 * {@see StoreStockOutRequest} apply; the live surviving-cap check (with the
 * original quantities returned to their batches first) is re-run inside
 * {@see CorrectRecord::stockOut()}.
 */
class CorrectStockOutRequest extends AppFormRequest
{
    protected $errorBag = 'stockout_correction';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $header = $this->route('stockOut');
        $headerId = $header?->stock_out_id ?? $header;

        return [
            'client_id' => ['required', 'integer', Client::activeExistsRule('client_id')],
            'release_date' => ['required', 'date', 'before_or_equal:today'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.id' => [
                'required',
                'integer',
                Rule::exists('stock_out_details', 'stock_out_detail_id')->where('stock_out_id', $headerId),
            ],
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
            'details.*.id.exists' => 'A stock-out line does not belong to this transaction.',
            'details.*.batch_id.required' => 'Please select a batch for every line.',
            'details.*.quantity.required' => 'A quantity is required for every line.',
            'details.*.quantity.min' => 'Every line quantity must be at least 1.',
            'details.*.unit.in' => "Every unit must be one of: {$units}.",
            'details.*.unit_price.numeric' => 'The unit price must be a number.',
            'details.*.unit_price.min' => 'The unit price cannot be negative.',
        ];
    }
}
