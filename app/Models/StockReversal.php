<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReversal extends Model
{
    protected $table = 'stock_reversals';

    protected $primaryKey = 'reversal_id';

    public $timestamps = false;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    protected $fillable = [
        'stock_in_id',
        'stock_out_id',
        'user_id',
        'quantity',
        'reason',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockIn::class, 'stock_in_id', 'stock_in_id');
    }

    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(StockOut::class, 'stock_out_id', 'stock_out_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Reversal type derived from which transaction reference is populated.
     * The ERD expresses this as "Stock_In_ID OR Stock_Out_ID".
     */
    public function type(): string
    {
        return $this->stock_in_id ? self::TYPE_IN : self::TYPE_OUT;
    }

    public function typeLabel(): string
    {
        return $this->type() === self::TYPE_IN ? 'Stock In' : 'Stock Out';
    }

    /**
     * The stock transaction being reversed (presentation helper).
     */
    public function source(): StockIn|StockOut|null
    {
        return $this->stockIn ?? $this->stockOut;
    }

    /**
     * Display reference of the original transaction (presentation only).
     */
    public function sourceReference(): string
    {
        $source = $this->source();

        if ($source instanceof StockIn) {
            return $source->reference();
        }

        if ($source instanceof StockOut) {
            return $source->reference();
        }

        return '—';
    }

    /**
     * The batch affected by the reversed transaction.
     */
    public function sourceBatch(): ?Batch
    {
        $source = $this->source();

        return $source?->batch;
    }

    /**
     * Display reference for a reversal (presentation only; not a DB column).
     */
    public function reference(): string
    {
        return 'REV-'.str_pad((string) $this->reversal_id, 3, '0', STR_PAD_LEFT);
    }
}
