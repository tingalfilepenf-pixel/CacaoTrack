<?php

namespace App\Http\Requests;

use App\Models\ScionVariety;
use App\Models\Supplier;
use Illuminate\Validation\Validator;

class StoreStockInRequest extends AppFormRequest
{
    protected $errorBag = 'stockin';

    /**
     * Fields that make a tab "filled". The prefilled date received is
     * deliberately absent: a date alone never counts as an entry, because it
     * arrives pre-populated with today's date.
     *
     * @var array<string, list<string>>
     */
    private const DATA_FIELDS = [
        'seedling' => ['supplier_id', 'quantity', 'origin', 'notes'],
        'scion' => ['supplier_id', 'quantity', 'origin', 'scion_variety_id', 'unit_price', 'notes'],
    ];

    /**
     * Drop untouched tabs and normalize empty strings before validation, so an
     * untouched tab is treated as absent even when the browser posts its empty
     * fields plus the prefilled date. The server is the only validator.
     */
    protected function prepareForValidation(): void
    {
        foreach (['seedling', 'scion'] as $tab) {
            $raw = $this->input($tab);

            if (! is_array($raw)) {
                $this->request->remove($tab);

                continue;
            }

            $entry = [
                'supplier_id' => $this->cleanValue($raw['supplier_id'] ?? null),
                'quantity' => $this->cleanValue($raw['quantity'] ?? null),
                'origin' => $this->cleanValue($raw['origin'] ?? null),
                'date_received' => $this->cleanValue($raw['date_received'] ?? null),
                'notes' => $this->cleanValue($raw['notes'] ?? null),
            ];

            if ($tab === 'scion') {
                $entry['scion_variety_id'] = $this->cleanValue($raw['scion_variety_id'] ?? null);
                $entry['unit_price'] = $this->cleanValue($raw['unit_price'] ?? null);
            }

            if (! $this->entryHasData($tab, $entry)) {
                $this->request->remove($tab);

                continue;
            }

            $this->merge([$tab => $entry]);
        }
    }

    /**
     * A tab is validated only when the user actually filled it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        if (is_array($this->input('seedling'))) {
            $rules += $this->rulesForTab('seedling', false);
        }

        if (is_array($this->input('scion'))) {
            $rules += $this->rulesForTab('scion', true);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_array($this->input('seedling')) && ! is_array($this->input('scion'))) {
                $validator->errors()->add(
                    'entries',
                    'Fill in the Seedlings tab, the Scion tab, or both.',
                );
            }
        });
    }

    /**
     * Canonical payload for the duplicate-submission fingerprint: untouched
     * tabs are already removed, and blank values plus the prefilled date are
     * ignored. Nested seedling[...] / scion[...] entries keep their own keys.
     *
     * @return array<string, array<string, mixed>>
     */
    public function duplicateFingerprintPayload(): array
    {
        $payload = [];

        foreach (array_keys(self::DATA_FIELDS) as $tab) {
            $entry = $this->validated()[$tab] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $normalized = [];

            foreach ($entry as $key => $value) {
                if ($key === 'date_received') {
                    continue;
                }

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }

                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }

            if ($normalized !== []) {
                $payload[$tab] = $normalized;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seedling.supplier_id.required' => 'Seedlings tab: select a supplier.',
            'seedling.supplier_id.exists' => 'Seedlings tab: the selected supplier is not available.',
            'seedling.quantity.required' => 'Seedlings tab: enter the received quantity.',
            'seedling.quantity.integer' => 'Seedlings tab: the quantity must be a whole number.',
            'seedling.quantity.min' => 'Seedlings tab: the quantity must be at least 1.',
            'seedling.origin.required' => 'Seedlings tab: enter the origin.',
            'seedling.origin.max' => 'Seedlings tab: the origin is too long.',
            'seedling.notes.max' => 'Seedlings tab: the notes are too long.',
            'seedling.date_received.required' => 'Seedlings tab: enter the date received.',
            'seedling.date_received.date' => 'Seedlings tab: enter a valid date received.',
            'seedling.date_received.before_or_equal' => 'Seedlings tab: the date received cannot be in the future.',
            'scion.supplier_id.exists' => 'Scion tab: the selected supplier is not available.',
            'scion.quantity.required' => 'Scion tab: enter the received quantity.',
            'scion.quantity.integer' => 'Scion tab: the quantity must be a whole number.',
            'scion.quantity.min' => 'Scion tab: the quantity must be at least 1.',
            'scion.origin.required' => 'Scion tab: enter the origin.',
            'scion.origin.max' => 'Scion tab: the origin is too long.',
            'scion.scion_variety_id.required' => 'Scion tab: select a scion variety.',
            'scion.scion_variety_id.exists' => 'Scion tab: the selected scion variety is not available.',
            'scion.unit_price.required' => 'Scion tab: enter a unit price (0 is allowed).',
            'scion.unit_price.numeric' => 'Scion tab: unit price must be a number.',
            'scion.unit_price.min' => 'Scion tab: unit price cannot be negative.',
            'scion.notes.max' => 'Scion tab: the notes are too long.',
            'scion.date_received.required' => 'Scion tab: enter the date received.',
            'scion.date_received.date' => 'Scion tab: enter a valid date received.',
            'scion.date_received.before_or_equal' => 'Scion tab: the date received cannot be in the future.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rulesForTab(string $tab, bool $isScion): array
    {
        $rules = [
            "{$tab}.supplier_id" => [
                $isScion ? 'nullable' : 'required',
                'integer',
                Supplier::activeExistsRule('supplier_id'),
            ],
            "{$tab}.quantity" => ['required', 'integer', 'min:1'],
            "{$tab}.origin" => ['required', 'string', 'max:255'],
            "{$tab}.notes" => ['nullable', 'string', 'max:1000'],
            "{$tab}.date_received" => ['required', 'date', 'before_or_equal:today'],
        ];

        if ($isScion) {
            $rules["{$tab}.scion_variety_id"] = [
                'required',
                'integer',
                ScionVariety::activeExistsRule('scion_variety_id'),
            ];
            $rules["{$tab}.unit_price"] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function entryHasData(string $tab, array $entry): bool
    {
        foreach (self::DATA_FIELDS[$tab] as $key) {
            $value = $entry[$key] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function cleanValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
