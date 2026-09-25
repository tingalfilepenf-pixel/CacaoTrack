<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOut extends Model
{
    protected $table = 'stock_outs';

    protected $primaryKey = 'stock_out_id';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'batch_id',
        'user_id',
        'quantity',
        'total_amount',
        'release_date',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'release_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
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
        return $this->hasMany(StockReversal::class, 'stock_out_id', 'stock_out_id');
    }

    /**
     * Quantity of this stock-out already reversed (RULE 6: single definition
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
     * Display reference for a stock-out transaction (presentation only; not
     * a database column).
     */
    public function reference(): string
    {
        return 'SO-'.str_pad((string) $this->stock_out_id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Total stock released for a batch (RULE 6: single definition shared by
     * the listing, inventory logic and analytics).
     */
    public static function totalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('quantity');
    }
}
