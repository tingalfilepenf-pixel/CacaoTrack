<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Client-approved ERD entity `Batch` (ERD §3.8).
 *
 * A batch is the established grafted cacao record. `total_grafted` is the batch
 * population, `regraft` is its regraft counter, and `available_count` is the
 * live available balance (never decremented by mortality). A regraft sub-batch
 * links to its parent through `parent_batch_id` and has its own 21-day cycle.
 * The monthly operational status lives in the related Inventory snapshots.
 *
 * Provisional default 1 (pending PRD `OD-3`): a newly established batch starts
 * with `available_count = total_grafted`.
 */
class Batch extends Model
{
    use Archivable;
    use HasFactory;

    protected $table = 'batches';

    protected $primaryKey = 'batch_id';

    public $timestamps = false;

    /**
     * Allowed Batch.Status values (ERD §3.8 declares the column but no
     * enumeration; RULE 6 single source of truth for the listing and validation).
     *
     * @var list<string>
     */
    public const STATUSES = ['active', 'inactive'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Relations that make a batch's counts immutable (RULE 6: one definition
     * shared by the controller and the listing). Activity_Log is deliberately
     * excluded so a freshly created batch stays editable.
     *
     * @var list<string>
     */
    public const OPERATIONAL_RELATIONS = ['mortalityRecords', 'replantingRecords', 'stockOutDetails'];

    protected $fillable = [
        'scion_variety_id',
        'parent_batch_id',
        'user_id',
        'batch_code',
        'total_grafted',
        'regraft',
        'available_count',
        'grafted_date',
        'created_at',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'regraft' => 'integer',
            'grafted_date' => 'date',
            'created_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function scionVariety(): BelongsTo
    {
        return $this->belongsTo(ScionVariety::class, 'scion_variety_id', 'scion_variety_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The parent batch when this row is a regraft sub-batch (ERD §3.8).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_batch_id', 'batch_id');
    }

    /**
     * Regraft sub-batches linked to this batch as their parent (ERD §3.8).
     */
    public function subBatches(): HasMany
    {
        return $this->hasMany(self::class, 'parent_batch_id', 'batch_id');
    }

    /**
     * Whether this row is a regraft sub-batch rather than a parent batch.
     */
    public function isSubBatch(): bool
    {
        return $this->parent_batch_id !== null;
    }

    public function mortalityRecords(): HasMany
    {
        return $this->hasMany(MortalityRecord::class, 'batch_id', 'batch_id');
    }

    public function replantingRecords(): HasMany
    {
        return $this->hasMany(ReplantingRecord::class, 'batch_id', 'batch_id');
    }

    /**
     * Monthly Inventory snapshots for this batch (many rows — the old
     * one-row-per-batch balance is gone).
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'batch_id', 'batch_id');
    }

    /**
     * The most recent monthly Inventory snapshot for this batch.
     */
    public function latestInventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'batch_id', 'batch_id')
            ->latestOfMany('inventory_date');
    }

    public function stockOutDetails(): HasMany
    {
        return $this->hasMany(StockOutDetail::class, 'batch_id', 'batch_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'batch_id', 'batch_id');
    }

    /**
     * Total effective mortality recorded against this batch.
     *
     * Mortality is tracked separately from the live available balance; the
     * effective quantity is the raw sum of related Mortality_Record rows.
     */
    public function effectiveMortalityQuantity(): int
    {
        return MortalityRecord::effectiveQuantityForBatch($this->batch_id);
    }

    /**
     * Remaining quantity that may still be recorded as mortality.
     *
     * Provisional default 2 (pending PRD `OD-4`): the mortality quantity is
     * bounded by the batch's grafted population, so the cap is
     * `max(0, total_grafted - effective mortality)`. Recording mortality still
     * does **not** mutate `available_count` or the `regraft` counter.
     */
    public function remainingMortalityCapacity(): int
    {
        return max(0, (int) $this->total_grafted - $this->effectiveMortalityQuantity());
    }

    /**
     * Total quantity replanted against this batch.
     */
    public function totalReplanted(): int
    {
        return ReplantingRecord::totalForBatch($this->batch_id);
    }

    /**
     * Replanting deficit for this batch.
     *
     * Replanting Deficit = effective mortality - total replanted (floor 0).
     * This is the single definition shared by the listing and validation
     * (RULE 6).
     */
    public function replantingDeficit(): int
    {
        return max(0, $this->effectiveMortalityQuantity() - $this->totalReplanted());
    }

    /**
     * Remaining quantity that may still be recorded as replanting.
     */
    public function remainingReplantingCapacity(): int
    {
        return $this->replantingDeficit();
    }

    /**
     * Total Available quantity KPI: the live available balance summed across
     * batches. This replaces the old `Inventory::totalActiveQuantity()` now
     * that the balance lives on `batches.available_count` (RULE 6). Archived
     * batches are included so dashboard/report figures stay stable (Session AJ).
     */
    public static function totalAvailableQuantity(): int
    {
        return (int) static::query()->sum('available_count');
    }

    /**
     * Whether this batch already has operational records that depend on its
     * counts. Deliberately excludes Activity_Log (batch lifecycle rows are
     * written with a NULL batch_id) so a freshly created batch stays editable.
     */
    public function hasOperationalRecords(): bool
    {
        foreach (self::OPERATIONAL_RELATIONS as $relation) {
            $attribute = Str::snake($relation).'_exists';

            if (array_key_exists($attribute, $this->attributes)) {
                if ((bool) $this->attributes[$attribute]) {
                    return true;
                }

                continue;
            }

            if ($this->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Centralized validation rules for Batch master data (RULE 6, FR-16).
     * `available_count` is never submitted: it is the live server-side balance.
     * `scion_variety_id` is nullable (a batch may exist before a variety is
     * assigned — ERD §3.8).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'batch_code' => ['required', 'string', 'max:255', Rule::unique('batches', 'batch_code')->ignore($ignoreId, 'batch_id')],
            'scion_variety_id' => ['nullable', 'integer', ScionVariety::activeExistsRule('scion_variety_id')],
            'total_grafted' => ['required', 'integer', 'min:1'],
            'grafted_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ];
    }
}
