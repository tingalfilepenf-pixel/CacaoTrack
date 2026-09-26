<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Revised ERD entity `Stock_In` (ERD §3.7).
 *
 * Records a receiving event. `quantity` is the ordered/received quantity and
 * `viable` is the usable quantity. For a **seedling** row `viable` is the
 * five-day recounted usable quantity (so it need not equal `quantity`). There
 * is deliberately **no** `batch_id`: the viability count happens before a Batch
 * is established.
 *
 * The client confirmed seedling Stock In and Scion Receiving are the same
 * real-world receiving event, so the former `Scion_Receiving` log was merged
 * into this table rather than kept separate (see migration
 * `2026_09_26_000003_add_scion_columns_to_stock_ins_table`). `scion_variety_id`
 * and `unit_price` are **optional per row**: a row either represents seedlings
 * only (both null) or includes scion (both set). There is deliberately **no**
 * "type"/discriminator column — the presence of `scion_variety_id` itself is
 * the signal (see `isScionEntry()`). For scion-inclusive rows `viable` is set
 * equal to `quantity` at entry because scion has no five-day recount
 * (perishable, used immediately), unlike seedlings.
 */
class StockIn extends Model
{
    use HasFactory;

    protected $table = 'stock_ins';

    protected $primaryKey = 'stock_in_id';

    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'scion_variety_id',
        'user_id',
        'quantity',
        'viable',
        'unit_price',
        'origin',
        'notes',
        'date_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'viable' => 'integer',
            'unit_price' => 'decimal:2',
            'date_received' => 'date',
        ];
    }

    /**
     * `viable` is a recount subset of the same physical shipment, so
     * `viable > quantity` is an impossible physical state. Enforced at the
     * model layer (locked business rule 5) because the schema is frozen (no DB
     * CHECK can be added without a migration) and controllers are out of scope
     * for this pass. The `rules()` (`lte:quantity`) provide the user-facing
     * message; this guard is the structural backstop.
     */
    protected static function booted(): void
    {
        static::saving(function (self $stockIn): void {
            if ((int) $stockIn->viable > (int) $stockIn->quantity) {
                throw new InvalidArgumentException('Viable quantity cannot exceed the received quantity.');
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    /**
     * Optional scion variety for a scion-inclusive receiving.
     *
     * Nullable on purpose: a pure-seedling stock-in has no scion variety. This
     * is presentation/relationship only — it is NOT a stored "type" flag; the
     * mere presence of `scion_variety_id` identifies a scion row.
     */
    public function scionVariety(): BelongsTo
    {
        return $this->belongsTo(ScionVariety::class, 'scion_variety_id', 'scion_variety_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Whether this stock-in includes scion material.
     *
     * There is no separate "type" column: a row is a scion entry when
     * `scion_variety_id` is set (and then `unit_price` is set too). A
     * seedling-only row leaves both null. Used by the controller/view to decide
     * how to render the row, never as a stored flag.
     */
    public function isScionEntry(): bool
    {
        return $this->scion_variety_id !== null;
    }

    /**
     * Display reference for a stock-in transaction (presentation only; not
     * a database column).
     */
    public function reference(): string
    {
        return 'SI-'.str_pad((string) $this->stock_in_id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Centralized validation rules for a Stock In record (RULE 6).
     *
     * A stock-in is either seedling-only (`scion_variety_id`/`unit_price` both
     * null) or scion-inclusive (both set). `required_with` each way expresses
     * "both or neither" without a discriminator column. `viable` is required
     * here for the seedling path; the scion path sets it server-side, so
     * `StoreStockInRequest` relaxes it only when `scion_variety_id` is present.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'viable' => ['required', 'integer', 'min:0', 'lte:quantity'],
            'scion_variety_id' => ['nullable', 'integer', ScionVariety::activeExistsRule('scion_variety_id'), 'required_with:unit_price'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'required_with:scion_variety_id'],
            'origin' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
