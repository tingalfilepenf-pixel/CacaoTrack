<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revised ERD entity `Mortality_Record` (ERD §3.10).
 *
 * Records mortality during the batch cycle. The `mortality_adjustments` table
 * was removed with the revised model, so effective mortality is simply the raw
 * recorded quantity (locked business rule 1).
 */
class MortalityRecord extends Model
{
    use HasFactory;

    protected $table = 'mortality_records';

    protected $primaryKey = 'mortality_id';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'user_id',
        'quantity',
        'cause',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
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
     * Effective mortality for this record. With adjustments removed, this is
     * the raw recorded quantity (RULE 6: the single shared definition).
     */
    public function effectiveQuantity(): int
    {
        return (int) $this->quantity;
    }

    public function getEffectiveQuantityAttribute(): int
    {
        return $this->effectiveQuantity();
    }

    /**
     * Total effective mortality recorded against a batch, optionally excluding
     * one record.
     */
    public static function effectiveQuantityForBatch(int $batchId, ?int $excludeMortalityId = null): int
    {
        $records = static::query()->where('batch_id', $batchId);

        if ($excludeMortalityId !== null) {
            $records->where('mortality_id', '!=', $excludeMortalityId);
        }

        return (int) $records->sum('quantity');
    }

    /**
     * Total effective mortality across a set of batches (set-based counterpart
     * of effectiveQuantityForBatch — RULE 6).
     *
     * @param  iterable<int, int|string>  $batchIds
     */
    public static function effectiveQuantityForBatches(iterable $batchIds): int
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return 0;
        }

        return (int) static::query()->whereIn('batch_id', $ids)->sum('quantity');
    }
}
