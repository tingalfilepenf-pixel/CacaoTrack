<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Batch extends Model
{
    use Archivable;

    protected $table = 'batches';

    protected $primaryKey = 'batch_id';

    public $timestamps = false;

    /**
     * Allowed Batch.Status values (ERD §2.6 declares the column but no
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
    public const OPERATIONAL_RELATIONS = ['mortalityRecords', 'replantingRecords', 'stockIns', 'stockOuts'];

    protected $fillable = [
        'variety_id',
        'scion_id',
        'pot_id',
        'user_id',
        'batch_code',
        'total_grafted',
        'viable_count',
        'available_count',
        'grafted_date',
        'monitoring_start_date',
        'created_at',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'grafted_date' => 'date',
            'monitoring_start_date' => 'date',
            'created_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class, 'variety_id', 'variety_id');
    }

    public function scion(): BelongsTo
    {
        return $this->belongsTo(Scion::class, 'scion_id', 'scion_id');
    }

    public function potType(): BelongsTo
    {
        return $this->belongsTo(PotType::class, 'pot_id', 'pot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mortalityRecords(): HasMany
    {
        return $this->hasMany(MortalityRecord::class, 'batch_id', 'batch_id');
    }

    public function replantingRecords(): HasMany
    {
        return $this->hasMany(ReplantingRecord::class, 'batch_id', 'batch_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'batch_id', 'batch_id');
    }

    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class, 'batch_id', 'batch_id');
    }

    public function stockOuts(): HasMany
    {
        return $this->hasMany(StockOut::class, 'batch_id', 'batch_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'batch_id', 'batch_id');
    }

    public function monitoringRecords(): HasMany
    {
        return $this->hasMany(BatchMonitoring::class, 'batch_id', 'batch_id');
    }

    /**
     * Total effective mortality recorded against this batch.
     */
    public function effectiveMortalityQuantity(): int
    {
        return MortalityRecord::effectiveQuantityForBatch($this->batch_id);
    }

    /**
     * The batch's current **alive plant count**.
     *
     * Single source of truth is the stored `viable_count`, which Mortality
     * Tracking decrements as plants die (approved change — mortality is the
     * only operation that updates plant status). Real stored data only (RULE 5/12).
     */
    public function alivePlants(): int
    {
        return max(0, (int) $this->viable_count);
    }

    /**
     * Apply a signed delta to the batch's alive count (stored `viable_count`),
     * floored at 0.
     *
     * Single writer for the alive count (RULE 6/7): Mortality Tracking calls it
     * with a negative delta when mortality is recorded and a positive delta when
     * a mortality adjustment is reversed. Callers must run inside a DB
     * transaction with the batch row locked.
     */
    public function applyAliveDelta(int $delta): void
    {
        $this->viable_count = max(0, (int) $this->viable_count + $delta);
        $this->save();
    }

    /**
     * Remaining alive plants that may still be recorded as mortality.
     *
     * Because `viable_count` is now the current alive count (mortality already
     * deducted), the remaining capacity is simply the alive count (RULE 6).
     */
    public function remainingMortalityCapacity(): int
    {
        return $this->alivePlants();
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
     * ERD/PRD business rule: replanting cannot exceed the mortality it replaces.
     * This is the single definition shared by the listing and validation (RULE 6).
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
     * Batch Monitoring lifecycle helpers (approved ERD revision §9.11).
     * All math is delegated to the single definition in `BatchMonitoring`
     * (RULE 6) — these are convenience wrappers for the batch itself.
     */
    public function monitoringElapsedDays(mixed $reference = null): ?int
    {
        return BatchMonitoring::elapsedDays($this->monitoring_start_date, $reference);
    }

    public function monitoringDay(mixed $reference = null): ?int
    {
        return BatchMonitoring::monitoringDay($this->monitoring_start_date, $reference);
    }

    public function monitoringRemainingDays(mixed $reference = null): ?int
    {
        return BatchMonitoring::remainingDays($this->monitoring_start_date, $reference);
    }

    public function isMonitoringComplete(mixed $reference = null): bool
    {
        return BatchMonitoring::lifecycleComplete($this->monitoring_start_date, $reference);
    }

    public function latestMonitoringRecord(): ?BatchMonitoring
    {
        return $this->monitoringRecords()
            ->orderByDesc('monitoring_date')
            ->orderByDesc('monitoring_id')
            ->first();
    }

    public function monitoringStatus(mixed $reference = null): string
    {
        return BatchMonitoring::statusFor($this, $reference);
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
     * Available count is never submitted: it is derived server-side from
     * viable_count (and the inventory balance) so the invariant holds.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'batch_code' => ['required', 'string', 'max:255', Rule::unique('batches', 'batch_code')->ignore($ignoreId, 'batch_id')],
            'variety_id' => ['required', 'integer', Variety::activeExistsRule('variety_id')],
            'scion_id' => ['required', 'integer', Scion::activeExistsRule('scion_id')],
            'pot_id' => ['required', 'integer', PotType::activeExistsRule('pot_id')],
            'total_grafted' => ['required', 'integer', 'min:1'],
            'viable_count' => ['required', 'integer', 'min:0', 'lte:total_grafted'],
            'grafted_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ];
    }
}
