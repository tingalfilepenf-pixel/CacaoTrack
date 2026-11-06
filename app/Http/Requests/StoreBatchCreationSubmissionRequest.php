<?php

namespace App\Http\Requests;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Support\StockBalances;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Capture one Batch Creation submission. The source decides which fields are
 * accepted: own nursery consumes seedlings/scions, supplier delivery records an
 * external delivery and consumes nothing.
 *
 * Live-state limits are checked here for a clear submission-time message and
 * are re-checked under locks at approval.
 */
class StoreBatchCreationSubmissionRequest extends AppFormRequest
{
    protected $errorBag = 'submission_batch';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $source = $this->input('source');

        $rules = [
            'source' => ['required', 'string', Rule::in(Batch::SOURCES)],
        ];

        if ($source === Batch::SOURCE_SUPPLIER) {
            return array_merge($rules, [
                'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
                'delivery_date' => ['required', 'date', 'before_or_equal:today'],
                'scion_variety_id' => ['required', 'integer', ScionVariety::activeExistsRule('scion_variety_id')],
                'plants_delivered' => ['required', 'integer', 'min:1'],
                'seedlings_used' => ['prohibited'],
                'scion_used' => ['prohibited'],
                'plants_grafted' => ['prohibited'],
            ]);
        }

        if ($source !== Batch::SOURCE_OWN) {
            return $rules;
        }

        return array_merge($rules, [
            'seedlings_used' => ['required', 'integer', 'min:1'],
            'scion_variety_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_numeric($value)
                        || ! ScionVariety::query()->receivedInStockIn()->whereKey($value)->exists()) {
                        $fail('Choose a scion variety.');
                    }
                },
            ],
            'scion_used' => ['required', 'integer', 'min:1'],
            'plants_grafted' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['prohibited'],
            'delivery_date' => ['prohibited'],
            'plants_delivered' => ['prohibited'],
        ]);
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('source') !== Batch::SOURCE_OWN) {
                    return;
                }

                if ($validator->errors()->hasAny([
                    'seedlings_used',
                    'scion_variety_id',
                    'scion_used',
                    'plants_grafted',
                ])) {
                    return;
                }

                $seedlingsUsed = (int) $this->input('seedlings_used');
                $scionUsed = (int) $this->input('scion_used');
                $plantsGrafted = (int) $this->input('plants_grafted');
                $variety = ScionVariety::find((int) $this->input('scion_variety_id'));

                if ($variety !== null) {
                    $availableScion = $variety->availableScionQuantity();

                    if ($scionUsed > $availableScion) {
                        $validator->errors()->add(
                            'scion_used',
                            "Only {$availableScion} {$variety->scion_variety_name} scion is available. Enter {$availableScion} or fewer.",
                        );
                    }
                }

                $availableSeedlings = StockBalances::seedlingsAvailable();

                if ($seedlingsUsed > $availableSeedlings) {
                    $validator->errors()->add(
                        'seedlings_used',
                        "Only {$availableSeedlings} seedlings are available. Enter {$availableSeedlings} or fewer.",
                    );
                }

                if ($plantsGrafted > min($seedlingsUsed, $scionUsed)) {
                    $validator->errors()->add(
                        'plants_grafted',
                        "Plants grafted can't be more than the seedlings or scion used.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Choose where the batch came from.',
            'source.in' => 'Choose where the batch came from.',
            'supplier_id.required' => 'Choose a supplier.',
            'supplier_id.exists' => 'Choose a supplier.',
            'delivery_date.required' => 'Enter the delivery date.',
            'delivery_date.before_or_equal' => 'The delivery date cannot be in the future.',
            'scion_variety_id.required' => 'Choose a scion variety.',
            'scion_variety_id.exists' => 'Choose a scion variety.',
            'plants_delivered.required' => 'Enter how many plants were delivered.',
            'plants_delivered.min' => 'Enter how many plants were delivered.',
            'seedlings_used.required' => 'Enter how many seedlings were used.',
            'seedlings_used.min' => 'Enter how many seedlings were used.',
            'scion_used.required' => 'Enter how many scion were used.',
            'scion_used.min' => 'Enter how many scion were used.',
            'plants_grafted.required' => 'Enter how many plants were grafted.',
            'plants_grafted.min' => 'Enter how many plants were grafted.',
        ];
    }
}
