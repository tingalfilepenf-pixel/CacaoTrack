<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MortalityRecord extends Model
{
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

    public function adjustments(): HasMany
    {
        return $this->hasMany(MortalityAdjustment::class, 'mortality_id', 'mortality_id');
    }

    /**
     * Effective mortality for this record after signed adjustments.
     *
     * Effective = original Quantity + sum of Adjustment_Quantity (RULE 6:
     * this is the single definition shared by listing, validation and analytics).
     */
    public function effectiveQuantity(): int
    {
        $adjustments = $this->relationLoaded('adjustments')
            ? $this->adjustments
            : $this->adjustments()->get();

        return (int) $this->quantity + (int) $adjustments->sum('adjustment_quantity');
    }

    public function getEffectiveQuantityAttribute(): int
    {
        return $this->effectiveQuantity();
    }

    /**
     * Total effective mortality recorded against a batch, optionally excluding
     * one record (used when adjusting that record).
     */
    public static function effectiveQuantityForBatch(int $batchId, ?int $excludeMortalityId = null): int
    {
        $records = static::query()->where('batch_id', $batchId);

        if ($excludeMortalityId !== null) {
            $records->where('mortality_id', '!=', $excludeMortalityId);
        }

        $base = (int) $records->sum('quantity');

        $adjustments = MortalityAdjustment::query()
            ->whereHas('mortalityRecord', function ($query) use ($batchId, $excludeMortalityId): void {
                $query->where('batch_id', $batchId);

                if ($excludeMortalityId !== null) {
                    $query->where('mortality_id', '!=', $excludeMortalityId);
                }
            })
            ->sum('adjustment_quantity');

        return $base + (int) $adjustments;
    }

    /**
     * Total effective mortality across a set of batches (RULE 6: the same
     * definition as effectiveQuantityForBatch, set-based for scion-level
     * aggregation).
     *
     * @param  iterable<int, int|string>  $batchIds
     */
    public static function effectiveQuantityForBatches(iterable $batchIds): int
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return 0;
        }

        $base = (int) static::query()->whereIn('batch_id', $ids)->sum('quantity');

        $adjustments = MortalityAdjustment::query()
            ->whereHas('mortalityRecord', fn ($query) => $query->whereIn('batch_id', $ids))
            ->sum('adjustment_quantity');

        return $base + (int) $adjustments;
    }
}
