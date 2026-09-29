<?php

namespace App\Models;

use App\Actions\RegraftBatch;
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
 * A batch is the established grafted cacao record. `total_grafted` is a
 * **cumulative, non-decreasing log** of plants grafted into the batch
 * (client-confirmed), `regraft` is its regraft counter, and `available_count`
 * is the live available balance (never decremented by mortality). The still-
 * attributable/live population of a batch is `total_grafted − regraft`. A
 * regraft sub-batch links to its parent through `parent_batch_id` and has its
 * own 21-day cycle. The monthly operational status lives in the related
 * Inventory snapshots.
 *
 * Provisional default 1 (pending PRD `OD-3`): a newly established batch starts
 * with `available_count = total_grafted`.
 *
 * Phase 7B regraft flow: a regraft moves a quantity out of a parent batch into
 * a linked sub-batch — see {@see RegraftBatch} — deducting `available_count`
 * and incrementing the `regraft` counter on the parent. `total_grafted` is left
 * unchanged (it is cumulative).
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
     * `subBatches` is included so a parent's counts are locked as soon as it
     * has been regrafted; otherwise its `total_grafted` could be edited after a
     * regraft and break the conservation invariant (Phase 7B).
     *
     * @var list<string>
     */
    public const OPERATIONAL_RELATIONS = ['mortalityRecords', 'regraftRecords', 'stockOutDetails', 'subBatches'];

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

    public function regraftRecords(): HasMany
    {
        return $this->hasMany(RegraftRecord::class, 'batch_id', 'batch_id');
    }

    /**
     * The regraft records that created this batch's sub-batches
     * (`regraft_records.parent_batch_id = this batch`, ERD §3.11 / Phase
     * 7B). A source batch's own `batch_id` rows are the records of the
     * sub-batches it created as a child, so the regraft history of a source is
     * read through this relation.
     */
    public function regraftRecordsAsParent(): HasMany
    {
        return $this->hasMany(RegraftRecord::class, 'parent_batch_id', 'batch_id');
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
     * The mortality quantity is bounded by the batch's still-attributable
     * population (`total_grafted − regraft`, client-confirmed), so the cap is
     * `max(0, (total_grafted - regraft) - effective mortality)`. Recording
     * mortality still does **not** mutate `available_count` or the `regraft`
     * counter.
     */
    public function remainingMortalityCapacity(): int
    {
        return max(0, ((int) $this->total_grafted - (int) $this->regraft) - $this->effectiveMortalityQuantity());
    }

    /**
     * Total quantity regrafted OUT of this batch — the cached
     * `batches.regraft` counter, incremented by the regraft action by exactly
     * the quantity moved into a linked sub-batch (Phase 7B).
     *
     * Provisional (pending PRD `OD-12`): the counter is the authoritative
     * per-batch regraft total. The method name is kept so the existing KPI /
     * listing code keeps working.
     */
    public function totalRegrafted(): int
    {
        return (int) $this->regraft;
    }

    /**
     * Quantity of this batch that may still be regrafted.
     *
     * A dead root stock cannot be regrafted, so the cap is the tightest of the
     * live available balance and the still-attributable, surviving population
     * (`total_grafted − regraft − effective mortality`, client-confirmed):
     * `max(0, min(available_count, (total_grafted - regraft) - effective mortality))`.
     */
    public function regraftableQuantity(): int
    {
        return max(0, min(
            (int) $this->available_count,
            ((int) $this->total_grafted - (int) $this->regraft) - $this->effectiveMortalityQuantity(),
        ));
    }

    /**
     * The next free sub-batch code for a regraft out of this batch.
     *
     * Provisional (pending PRD `OD-6`): the code is this batch's `batch_code`
     * plus `-R` and the first free sequence number (`B001` → `B001-R1`,
     * `B001-R2`, …). A sub-batch may itself be regrafted, producing a nested
     * code (`B001-R1` → `B001-R1-R1`). Call only while the source row is
     * locked so the uniqueness check cannot race.
     */
    public function nextSubBatchCode(): string
    {
        $sequence = 1;

        do {
            $candidate = $this->batch_code.'-R'.$sequence;
            $sequence++;
        } while (self::query()->where('batch_code', $candidate)->exists());

        return $candidate;
    }

    /**
     * Regraft deficit for this batch.
     *
     * Regraft Deficit = effective mortality - total regrafted (floor 0).
     * This is the single definition shared by the listing and validation
     * (RULE 6). The formula is unchanged from Phase 7A; `totalRegrafted()` now
     * reads the cached `batches.regraft` counter (provisional, `OD-12`).
     */
    public function regraftDeficit(): int
    {
        return max(0, $this->effectiveMortalityQuantity() - $this->totalRegrafted());
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
