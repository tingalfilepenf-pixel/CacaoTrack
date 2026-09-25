<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P5 approved schema exception: `Regraft_Record`.
 *
 * Records Regraft as a discrete, DATED event against a batch. It mirrors
 * `MortalityRecord` / `ReplantingRecord` (belongs to a Batch and a User, with a
 * centralized `totalForBatch` aggregate). The monthly `inventories.regraft`
 * aggregate is left untouched — this is an additional, more granular record.
 *
 * There is deliberately **no** controller/route/view for directly managing this
 * table in P5; the Stock Ledger report reads it. A recording UI is a separate
 * follow-up (flagged, not built silently).
 */
class RegraftRecord extends Model
{
    protected $table = 'regraft_records';

    protected $primaryKey = 'regraft_id';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'user_id',
        'quantity',
        'regraft_date',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'regraft_date' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total regrafted quantity for a batch (RULE 6: single definition shared by
     * the stock ledger and any future reporting).
     */
    public static function totalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('quantity');
    }
}
