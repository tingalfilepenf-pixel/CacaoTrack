<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Revised ERD entity `Stock_Out` (ERD §3.14).
 *
 * Header-only Stock Out / Pull Out transaction: the released quantities live
 * on its `Stock_Out_Detail` lines (one per batch/scion-variety).
 */
class StockOut extends Model
{
    protected $table = 'stock_outs';

    protected $primaryKey = 'stock_out_id';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOutDetail::class, 'stock_out_id', 'stock_out_id');
    }

    /**
     * Total released quantity across the header's detail lines (RULE 6).
     */
    public function totalQuantity(): int
    {
        return (int) $this->details()->sum('quantity');
    }

    /**
     * Display reference for a stock-out transaction (presentation only; not
     * a database column).
     */
    public function reference(): string
    {
        return 'SO-'.str_pad((string) $this->stock_out_id, 3, '0', STR_PAD_LEFT);
    }
}
