<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /**
     * The "Others" sentinel cause. Selecting it requires the user to supply a
     * free-text description, which is folded into the single `cause` column
     * (the ERD `Mortality_Record` has no separate description attribute).
     */
    public const CAUSE_OTHERS = 'Others';

    /**
     * Fixed cause-of-death choices (PRD FR-04 "Cause"), plus `Others`. This is
     * the single source of truth (RULE 6) for the Mortality form's dropdown.
     *
     * @var list<string>
     */
    public const CAUSES = [
        'Disease',
        'Pest',
        'Drought',
        'Physical Damage',
        self::CAUSE_OTHERS,
    ];

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
     * Resolve the stored `cause` value from the dropdown choice. When the user
     * picks `Others`, the required description is appended so the specific
     * cause is preserved (RULE 6: one shared definition).
     */
    public static function composeCause(string $cause, ?string $description = null): string
    {
        if ($cause !== self::CAUSE_OTHERS) {
            return $cause;
        }

        $description = trim((string) $description);

        return $description === '' ? self::CAUSE_OTHERS : self::CAUSE_OTHERS.': '.$description;
    }

    /**
     * Collapse a stored `cause` value to its reporting category. `Others` (with
     * or without a free-text specification, i.e. `Others: <description>`) is a
     * single mortality category for analytics — the description is only a
     * specification, not a distinct cause (RULE 6: one shared definition, so the
     * matrix, PDF and CSV all group alike). A blank cause reports as
     * `Unspecified`; every other cause is returned unchanged.
     */
    public static function normalizeCause(?string $cause): string
    {
        $cause = trim((string) $cause);

        if ($cause === '') {
            return 'Unspecified';
        }

        if ($cause === self::CAUSE_OTHERS || str_starts_with($cause, self::CAUSE_OTHERS.':')) {
            return self::CAUSE_OTHERS;
        }

        return $cause;
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

    /**
     * Per-batch effective mortality keyed by `batch_id` (set-based counterpart
     * of effectiveQuantityForBatch, for batch summaries — RULE 6). Avoids an
     * N+1 of per-batch lookups.
     *
     * @param  iterable<int, int|string>  $batchIds
     * @return Collection<int, int>
     */
    public static function effectiveQuantityByBatch(iterable $batchIds): Collection
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return collect();
        }

        return static::query()
            ->whereIn('batch_id', $ids)
            ->selectRaw('batch_id, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id')
            ->map(fn ($total) => (int) $total);
    }
}
