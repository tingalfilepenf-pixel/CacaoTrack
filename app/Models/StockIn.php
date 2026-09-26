<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Revised ERD entity `Stock_In` (ERD §3.7).
 *
 * Records seedlings received through the Stock In process. `quantity` is the
 * ordered/received quantity and `viable` is the five-day recounted usable
 * quantity (they need not be equal). There is deliberately **no** `batch_id`:
 * the viability count happens before a Batch is established.
 */
class StockIn extends Model
{
    use HasFactory;

    protected $table = 'stock_ins';

    protected $primaryKey = 'stock_in_id';

    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'quantity',
        'viable',
        'origin',
        'date_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'viable' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'viable' => ['required', 'integer', 'min:0', 'lte:quantity'],
            'origin' => ['required', 'string', 'max:255'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
