<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

/**
 * Revised ERD entity `Stock_Out_Detail` (ERD §3.15).
 *
 * One line per batch/scion-variety quantity inside a Stock Out header. The
 * `unit` is a fixed-list value (locked decision b) and `unit_price`/`amount`
 * are optional.
 */
class StockOutDetail extends Model
{
    use HasFactory;

    protected $table = 'stock_out_details';

    protected $primaryKey = 'stock_out_detail_id';

    public $timestamps = false;

    /**
     * The fixed list of accepted units (locked decision b: the unit column is
     * a fixed list, not free text). Single source of truth (RULE 6).
     *
     * @var list<string>
     */
    public const UNITS = ['seedlings', 'pieces', 'bags'];

    protected $fillable = [
        'stock_out_id',
        'batch_id',
        'scion_variety_id',
        'quantity',
        'unit',
        'unit_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * `Scion_Variety_ID` is always derived server-side from the referenced
     * Batch's own `scion_variety_id` (ERD §3.8: a Batch has one scion variety).
     * Any client-supplied value is overwritten here, so the invariant holds no
     * matter which controller writes the line (locked business rule 4).
     */
    protected static function booted(): void
    {
        static::saving(function (self $detail): void {
            if ($detail->batch_id !== null) {
                $detail->scion_variety_id = Batch::query()
                    ->whereKey($detail->batch_id)
                    ->value('scion_variety_id');
            }
        });
    }

    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(StockOut::class, 'stock_out_id', 'stock_out_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function scionVariety(): BelongsTo
    {
        return $this->belongsTo(ScionVariety::class, 'scion_variety_id', 'scion_variety_id');
    }

    /**
     * Line amount when a unit price was recorded (presentation/derivation
     * helper — null when no unit price applies).
     */
    public function lineAmount(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }

        return (float) $this->unit_price * (int) $this->quantity;
    }

    /**
     * Centralized validation rules for a Stock Out detail line (RULE 6).
     * `scion_variety_id` is deliberately absent: it is derived from the batch
     * server-side, never accepted as client input.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'stock_out_id' => ['required', 'integer', 'exists:stock_outs,stock_out_id'],
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', Rule::in(self::UNITS)],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
