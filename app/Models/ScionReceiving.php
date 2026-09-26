<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * New entity `Scion_Receiving`.
 *
 * Records the receipt of scion material for a given Scion Variety. This is a
 * standalone, additive record:
 *
 * - it does **not** add or hold any stock balance on `scion_varieties`;
 * - it does **not** touch `ReplantingRecord` (Regraft and Replanting are the
 *   same recorded procedure);
 * - there is deliberately **no** controller/route/view yet (schema + model
 *   only) pending confirmation of how usage should reference receiving
 *   records for traceability.
 *
 * `supplier_id` is nullable because scions are not always tied to a formal
 * Supplier record.
 *
 * A freshness/viability rule is planned but deliberately NOT implemented yet
 * (no `usable_until` column, no warning, no blocking) pending client
 * confirmation of the allowed viability window and whether it should warn or
 * block.
 */
class ScionReceiving extends Model
{
    use HasFactory;

    protected $table = 'scion_receivings';

    protected $primaryKey = 'scion_receiving_id';

    public $timestamps = false;

    protected $fillable = [
        'scion_variety_id',
        'supplier_id',
        'quantity',
        'unit_price',
        'date_received',
        'origin',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'date_received' => 'date',
        ];
    }

    public function scionVariety(): BelongsTo
    {
        return $this->belongsTo(ScionVariety::class, 'scion_variety_id', 'scion_variety_id');
    }

    /**
     * Nullable: a receiving is not always tied to a formal Supplier record.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    /**
     * Display reference for a scion receiving (presentation only; not a
     * database column).
     */
    public function reference(): string
    {
        return 'SCR-'.str_pad((string) $this->scion_receiving_id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Centralized validation rules for a Scion Receiving record (RULE 6).
     *
     * Supplier is optional; when present it must be an ACTIVE (non-archived)
     * Supplier (ERD §9.8). The scion variety must be an active master record.
     * Deliberately no freshness/viability rule here yet (out of scope).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'scion_variety_id' => ['required', 'integer', ScionVariety::activeExistsRule('scion_variety_id')],
            'supplier_id' => ['nullable', 'integer', Supplier::activeExistsRule('supplier_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
            'origin' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
