<?php

namespace App\Models;

use App\Actions\RegraftBatch;
use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
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
     * The company's 21-day production cycle (PRD §2.6, ERD §3.8/§5). The cycle
     * is not stored; its end is derived as `Grafted_Date + 21 days` (OD-7).
     */
    public const CYCLE_DAYS = 21;

    /**
     * Derived lifecycle statuses shown on the Inventory listing (owner-directed
     * display mapping; OD-7). They do NOT change the stored `status` column,
     * whose enumeration stays `active` / `inactive`.
     */
    public const LIFECYCLE_GROWING = 'growing';

    public const LIFECYCLE_READY = 'ready';

    public const LIFECYCLE_COMPLETED = 'completed';

    public const LIFECYCLE_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    public const LIFECYCLE_STATUSES = [
        self::LIFECYCLE_GROWING,
        self::LIFECYCLE_READY,
        self::LIFECYCLE_COMPLETED,
        self::LIFECYCLE_CANCELLED,
    ];

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

    /**
     * The date this batch reaches the end of its 21-day production cycle
     * (`grafted_date + 21 days`, OD-7: the cycle is derived, never stored).
     */
    public function cycleReadyDate(): ?Carbon
    {
        return $this->grafted_date?->copy()->addDays(self::CYCLE_DAYS);
    }

    /**
     * Derived lifecycle status for the Inventory listing (owner-directed
     * display mapping; the stored `status` column is left untouched):
     *
     *  - `cancelled`  : invalidated record — stored `inactive` or archived.
     *  - `completed`  : no remaining available quantity (fully stocked out).
     *  - `ready`      : the 21-day cycle has been reached.
     *  - `growing`    : still inside the 21-day cycle.
     */
    public function lifecycleStatus(): string
    {
        if ($this->status === self::STATUS_INACTIVE || $this->isArchived()) {
            return self::LIFECYCLE_CANCELLED;
        }

        if ((int) $this->available_count <= 0) {
            return self::LIFECYCLE_COMPLETED;
        }

        $readyDate = $this->cycleReadyDate();

        return $readyDate !== null && ! $readyDate->isFuture()
            ? self::LIFECYCLE_READY
            : self::LIFECYCLE_GROWING;
    }

    /**
     * UI labels for the derived lifecycle statuses (RULE 6: one source for the
     * listing badge and the status filter).
     *
     * @return array<string, string>
     */
    public static function lifecycleLabels(): array
    {
        return [
            self::LIFECYCLE_GROWING => 'Growing',
            self::LIFECYCLE_READY => 'Ready',
            self::LIFECYCLE_COMPLETED => 'Completed',
            self::LIFECYCLE_CANCELLED => 'Cancelled/Void',
        ];
    }

    /**
     * Query equivalent of {@see lifecycleStatus()} (same precedence) so the
     * Inventory status filter returns exactly the rows whose badge shows the
     * selected status.
     */
    public function scopeWhereLifecycleStatus(Builder $query, string $status): Builder
    {
        $readyCutoff = now()->startOfDay()->subDays(self::CYCLE_DAYS)->toDateString();

        return match ($status) {
            self::LIFECYCLE_CANCELLED => $query->where(fn (Builder $query) => $query
                ->where('status', self::STATUS_INACTIVE)
                ->orWhereNotNull('archived_at')),
            self::LIFECYCLE_COMPLETED => $query->where('status', self::STATUS_ACTIVE)
                ->whereNull('archived_at')
                ->where('available_count', '<=', 0),
            self::LIFECYCLE_READY => $query->where('status', self::STATUS_ACTIVE)
                ->whereNull('archived_at')
                ->where('available_count', '>', 0)
                ->whereDate('grafted_date', '<=', $readyCutoff),
            self::LIFECYCLE_GROWING => $query->where('status', self::STATUS_ACTIVE)
                ->whereNull('archived_at')
                ->where('available_count', '>', 0)
                ->whereDate('grafted_date', '>', $readyCutoff),
            default => $query,
        };
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
     * This batch's id plus every descendant reachable through
     * `parent_batch_id`, breadth-first (B001 -> B001-R1 -> B001-R1-R1).
     *
     * One indexed `whereIn(parent_batch_id)` query per generation, so the cost
     * is O(family depth) queries and the walk stops as soon as a generation has
     * no children. A `visited` set keeps it terminating even if a cycle were
     * ever introduced (it cannot be while {@see RegraftBatch} only ever links a
     * new sub-batch to an existing source). Current data max depth is 2.
     *
     * @return list<int>
     */
    public function familyBatchIds(): array
    {
        $rootId = (int) $this->batch_id;
        $ids = [$rootId];
        $visited = [$rootId => true];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $children = self::query()
                ->whereIn('parent_batch_id', $frontier)
                ->pluck('batch_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => isset($visited[$id]))
                ->values();

            if ($children->isEmpty()) {
                break;
            }

            foreach ($children as $id) {
                $visited[$id] = true;
                $ids[] = $id;
            }

            $frontier = $children->all();
        }

        return $ids;
    }

    /**
     * Family mortality: effective mortality for this batch AND every descendant
     * sub-batch (recursively). For a batch with no sub-batches it equals the
     * existing per-batch {@see effectiveMortalityQuantity()} exactly — that
     * single-batch figure is left untouched.
     *
     * This is an ADDITIONAL, display-only figure. The two headline KPIs are
     * deliberately unchanged: the Crude Mortality Rate numerator and the Gap
     * Report already sum each batch's own `effectiveMortalityQuantity()` across
     * the full batch list (each batch counted exactly once), so they must NOT
     * use this rollup — applying it per batch would double-count every
     * sub-batch once per ancestor.
     */
    public function familyMortality(): int
    {
        return MortalityRecord::effectiveQuantityForBatches($this->familyBatchIds());
    }

    /**
     * Remaining quantity that may still be recorded as mortality.
     *
     * This is the batch's live remaining balance — the SAME shared cap as
     * Stock Out and Regraft ({@see survivingQuantity()}): the establishment
     * quantity net of regraft-out, Stock Out/Pull-Out and recorded mortality.
     * Stock Out therefore reduces the mortality cap, so plants already released
     * can never be recorded as deaths afterwards. Recording mortality still
     * does **not** mutate `available_count` or the `regraft` counter.
     */
    public function remainingMortalityCapacity(): int
    {
        return $this->survivingQuantity();
    }

    /**
     * The separate quantity figures exposed by the Mortality Tracking batch
     * dropdown, each taken from its own authoritative source so a live balance
     * is never mistaken for the historical original quantity:
     *
     *  - `original_quantity`  : `total_grafted`, the cumulative, non-decreasing
     *                           establishment log; Stock Out never reduces it
     *                           (client-confirmed).
     *  - `stock_out_quantity` : sum of this batch's Stock Out detail lines.
     *  - `pullout_quantity`   : in CacaoTrack "Pull-Out" and "Stock Out" are the
     *                           same recorded transaction (PRD §2.8), so this
     *                           equals the Stock Out total.
     *  - `mortality_quantity` : effective recorded mortality.
     *  - `remaining_quantity` : the live balance after regraft, Stock Out and
     *                           mortality ({@see survivingQuantity()}).
     *
     * @return array{original_quantity:int,stock_out_quantity:int,pullout_quantity:int,mortality_quantity:int,remaining_quantity:int}
     */
    public function quantityBreakdown(): array
    {
        $stockOut = (int) $this->stockOutDetails()->sum('quantity');
        $mortality = $this->effectiveMortalityQuantity();

        return [
            'original_quantity' => (int) $this->total_grafted,
            'stock_out_quantity' => $stockOut,
            'pullout_quantity' => $stockOut,
            'mortality_quantity' => $mortality,
            'remaining_quantity' => $this->survivingQuantity($mortality),
        ];
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
     * The surviving / releasable quantity of this batch — the single cap shared
     * by the regraft flow and Stock Out.
     *
     * The client-confirmed surviving population subtracts EVERY outflow from the
     * batch's establishment quantity, each once:
     * `total_grafted − regraft − effective mortality − stock_out`.
     *
     * The live `available_count` already nets out regraft-out and Stock Out (it is
     * set to `total_grafted` on create, then decremented by {@see RegraftBatch}
     * and the Stock Out controller); mortality is the only outflow it does not
     * reflect. So `available_count − effective mortality` is exactly the
     * expression above, tracked from the authoritative live balance. Deriving it
     * from `available_count` (rather than re-summing the Stock Out history) also
     * keeps the record-correction flow correct, because that flow temporarily
     * restores `available_count` before re-validating an edited release.
     *
     * Earlier this method instead took the tighter of two one-sided exclusions
     * (`min(available_count, (total_grafted − regraft) − mortality)`), which
     * subtracted only the larger of mortality / stock-out and thus over-stated
     * the releasable balance whenever a batch had BOTH — it let dead plants be
     * released. Subtracting both aligns the Stock Out gate with the Stock Ledger's
     * running "Remaining Available" balance.
     *
     * It is used by {@see RegraftBatch} as the regraft cap and by the Stock Out
     * controller as the release cap, so plants already recorded as mortality can
     * never be regrafted or released.
     *
     * The optional `$mortalityQuantity` lets a caller that has already summed the
     * batch's mortality reuse it instead of issuing a second query, while still
     * going through this one definition (RULE 6).
     */
    public function survivingQuantity(?int $mortalityQuantity = null): int
    {
        $mortality = $mortalityQuantity ?? $this->effectiveMortalityQuantity();

        return max(0, (int) $this->available_count - $mortality);
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
     * The next free root batch code (FR-06): `B` + the next sequential
     * 3-digit number (`B001`, `B002`, …). Regraft sub-batch codes
     * (`B001-R1`) and any other non-`B<digits>` codes are ignored, so the
     * sequence always continues past the highest established root batch.
     *
     * Called inside the create transaction; the `batch_code` unique index
     * remains the final guard against a duplicate.
     */
    public static function nextBatchCode(): string
    {
        $highest = self::query()
            ->where('batch_code', 'like', 'B%')
            ->pluck('batch_code')
            ->reduce(function (int $carry, string $code): int {
                return preg_match('/^B(\d+)$/', $code, $matches)
                    ? max($carry, (int) $matches[1])
                    : $carry;
            }, 0);

        return 'B'.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Regraft deficit for this batch.
     *
     * Regraft Deficit = effective mortality - total regrafted (floor 0).
     * This is the single definition shared by the listing and validation
     * (RULE 6). The formula is unchanged from Phase 7A; `totalRegrafted()` now
     * reads the cached `batches.regraft` counter (provisional, `OD-12`).
     *
     * The optional `$mortalityQuantity` lets a caller that has already summed
     * the batch's mortality (e.g. an eager-loaded `withSum` alias) reuse it
     * instead of issuing a second query, while still going through this one
     * definition — the same pattern as {@see survivingQuantity()}.
     */
    public function regraftDeficit(?int $mortalityQuantity = null): int
    {
        $mortality = $mortalityQuantity ?? $this->effectiveMortalityQuantity();

        return max(0, $mortality - $this->totalRegrafted());
    }

    /**
     * Gross available quantity: the live available balance summed across ALL
     * batches, archived included. This replaces the old
     * `Inventory::totalActiveQuantity()`. NOTE: the "Total Active Inventory" KPI
     * does NOT use this gross sum — `AnalyticsMetrics::totalActiveInventory()`
     * applies the `active()` scope (archived batches excluded) and reports the
     * archived portion separately via `AnalyticsMetrics::totalArchivedInventory()`.
     */
    public static function totalAvailableQuantity(): int
    {
        return (int) static::query()->sum('available_count');
    }

    /**
     * Whether this batch has activity that DEPENDS on it as a regraft
     * sub-batch: recorded mortality, a stock-out, or a further regraft out of
     * it. Used by the record-correction flow to lock a regraft transaction once
     * the sub-batch it created has been used.
     *
     * Deliberately different from {@see hasOperationalRecords()}, which counts a
     * sub-batch's own creation `regraft_records` row (that row's `batch_id` is
     * the sub-batch) and therefore reports true for every regraft-created
     * batch. Here the batch's own creation record is ignored; only downstream
     * activity locks it.
     */
    public function hasDependentActivity(): bool
    {
        return $this->mortalityRecords()->exists()
            || $this->stockOutDetails()->exists()
            || $this->subBatches()->exists()
            || (int) $this->regraft > 0;
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
     * Why this batch cannot be hard-deleted (empty = deletable). Every
     * dependent record is restrictOnDelete, so a batch with any operational
     * history — mortality, regraft (as sub-batch creation or as parent of
     * sub-batches), stock-out lines, inventory snapshots or activity history —
     * must be kept so no traceability is destroyed.
     *
     * @return list<string>
     */
    public function deletionBlockers(): array
    {
        $checks = [
            'mortalityRecords' => 'mortality records',
            'regraftRecords' => 'regraft records',
            'subBatches' => 'regraft sub-batches',
            'stockOutDetails' => 'stock-out lines',
            'inventories' => 'inventory snapshots',
            'activityLogs' => 'activity history',
        ];

        $blockers = [];

        foreach ($checks as $relation => $label) {
            $attribute = Str::snake($relation).'_exists';

            $exists = array_key_exists($attribute, $this->attributes)
                ? (bool) $this->attributes[$attribute]
                : $this->{$relation}()->exists();

            if ($exists) {
                $blockers[] = $label;
            }
        }

        return $blockers;
    }

    /**
     * Centralized delete rule (RULE 6): a batch may only be deleted when it
     * has no dependent operational records.
     */
    public function canBeDeleted(): bool
    {
        return $this->deletionBlockers() === [];
    }

    /**
     * Validation rules for ESTABLISHING a new batch (RULE 6, FR-06).
     *
     * `Batch_Code` and `Grafted_Date` are system-assigned when a batch is
     * established — the next free code ({@see nextBatchCode()}) and today's
     * date — so they are deliberately not accepted from the client. Only the
     * operator's own choices are validated. `available_count` is never
     * submitted: it is the live server-side balance. `scion_variety_id` is
     * nullable (a batch may exist before a variety is assigned — ERD §3.8).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function storeRules(): array
    {
        return [
            'scion_variety_id' => ['nullable', 'integer', ScionVariety::activeExistsRule('scion_variety_id')],
            'total_grafted' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ];
    }

    /**
     * Centralized validation rules for editing an existing batch (RULE 6,
     * FR-16). `available_count` is never submitted: it is the live server-side
     * balance. `scion_variety_id` is nullable (a batch may exist before a
     * variety is assigned — ERD §3.8).
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
