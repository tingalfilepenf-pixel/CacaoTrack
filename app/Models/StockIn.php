<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIn extends Model
{
    protected $table = 'stock_ins';

    protected $primaryKey = 'stock_in_id';

    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'batch_id',
        'user_id',
        'quantity',
        'scion_quantity',
        'date_received',
    ];

    protected function casts(): array
    {
        return [
            'scion_quantity' => 'integer',
            'date_received' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(StockReversal::class, 'stock_in_id', 'stock_in_id');
    }

    /**
     * Quantity of this stock-in already reversed (RULE 6: single definition
     * shared by the listing and reversal validation).
     */
    public function reversedQuantity(): int
    {
        return (int) $this->reversals()->sum('quantity');
    }

    /**
     * Quantity still eligible for reversal.
     * FR-10: a reversal quantity must not exceed the reversible quantity.
     */
    public function reversibleQuantity(): int
    {
        return max(0, (int) $this->quantity - $this->reversedQuantity());
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
     * Total stock received for a batch (RULE 6: single definition shared by
     * the listing, inventory logic and analytics).
     */
    public static function totalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('quantity');
    }

    /**
     * Total scions received for a batch (approved ERD revision §9.9; RULE 6:
     * single definition shared by the Scion Management cards and reporting).
     */
    public static function scionTotalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('scion_quantity');
    }

    /**
     * Total scions received across a set of batches (set-based counterpart of
     * scionTotalForBatch — RULE 6).
     *
     * @param  iterable<int, int|string>  $batchIds
     */
    public static function scionTotalForBatches(iterable $batchIds): int
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return 0;
        }

        return (int) static::query()->whereIn('batch_id', $ids)->sum('scion_quantity');
    }
}
