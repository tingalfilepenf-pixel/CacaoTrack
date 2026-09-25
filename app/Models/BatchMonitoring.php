<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Daily plant-status observation for a batch's 21-day monitoring lifecycle.
 *
 * Approved ERD revision §9.11: the `Batch_Monitoring` entity gives the Batch
 * Monitoring feature its complete history without duplicating any `Batch` data.
 * Every lifecycle calculation (day, remaining days, batch status) is defined
 * once here and shared by the controller and the view (RULE 6).
 */
class BatchMonitoring extends Model
{
    protected $table = 'batch_monitorings';

    protected $primaryKey = 'monitoring_id';

    public $timestamps = false;

    /**
     * Length of the monitoring lifecycle, in days (single source of truth).
     */
    public const LIFECYCLE_DAYS = 21;

    /**
     * Observed plant conditions (single source of truth for the form and
     * validation). The key is stored; the value is the display label.
     *
     * @var array<string, string>
     */
    public const PLANT_STATUSES = [
        'healthy' => 'Healthy',
        'unhealthy' => 'Unhealthy',
        'dead' => 'Dead',
    ];

    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_UNHEALTHY = 'unhealthy';

    public const STATUS_DEAD = 'dead';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETE = 'complete';

    /**
     * Derived batch monitoring statuses (never stored — computed from the
     * batch's lifecycle only). Two states:
     *   - `Pending`  : the 21-day lifecycle has not completed yet.
     *   - `Complete` : the batch reached the full 21-day lifecycle.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'complete' => 'Complete',
    ];

    /**
     * Badge class per derived monitoring status (presentation only).
     *
     * @var array<string, string>
     */
    private const STATUS_BADGES = [
        'pending' => 'bg-warning text-dark',
        'complete' => 'bg-success',
    ];

    /**
     * Badge class per observed plant condition (presentation only).
     *
     * @var array<string, string>
     */
    private const PLANT_STATUS_BADGES = [
        'healthy' => 'bg-success',
        'unhealthy' => 'bg-warning text-dark',
        'dead' => 'bg-danger',
    ];

    protected $fillable = [
        'batch_id',
        'user_id',
        'monitoring_date',
        'monitoring_day',
        'status',
        'survivors',
        'mortality_quantity',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_date' => 'date',
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
     * Days elapsed since the monitoring start date (floor 0). NULL when the
     * batch has no monitoring start date. RULE 6: single lifecycle definition.
     */
    public static function elapsedDays(mixed $startDate, mixed $reference = null): ?int
    {
        if (empty($startDate)) {
            return null;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $reference = $reference ? Carbon::parse($reference)->startOfDay() : Carbon::today();

        return max(0, $start->diffInDays($reference));
    }

    /**
     * The 21-day lifecycle day number (1-based) for a reference date.
     */
    public static function monitoringDay(mixed $startDate, mixed $reference = null): ?int
    {
        $elapsed = self::elapsedDays($startDate, $reference);

        return $elapsed === null ? null : $elapsed + 1;
    }

    /**
     * Complete days remaining in the lifecycle (floor 0).
     */
    public static function remainingDays(mixed $startDate, mixed $reference = null): ?int
    {
        $day = self::monitoringDay($startDate, $reference);

        return $day === null ? null : max(0, self::LIFECYCLE_DAYS - $day);
    }

    /**
     * Whether the batch has finished its 21-day lifecycle at the reference date.
     */
    public static function lifecycleComplete(mixed $startDate, mixed $reference = null): bool
    {
        $elapsed = self::elapsedDays($startDate, $reference);

        return $elapsed !== null && $elapsed >= self::LIFECYCLE_DAYS;
    }

    /**
     * Derived batch monitoring status (RULE 6: single definition shared by the
     * listing and the view — never stored).
     *
     * The batch stays `Pending` until it reaches the full 21-day lifecycle and
     * is automatically `Complete` once it does (day > 21). A batch with no
     * monitoring start date has not reached the lifecycle and is `Pending`.
     *
     * The lifecycle is calculated from `Batch.Monitoring_Start_Date`.
     */
    public static function statusFor(Batch $batch, mixed $reference = null): string
    {
        return self::lifecycleComplete($batch->monitoring_start_date, $reference)
            ? self::STATUS_COMPLETE
            : self::STATUS_PENDING;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? Str::headline($status);
    }

    public static function statusBadgeClass(string $status): string
    {
        return self::STATUS_BADGES[$status] ?? 'bg-secondary';
    }

    public static function plantStatusLabel(string $status): string
    {
        return self::PLANT_STATUSES[$status] ?? Str::headline($status);
    }

    public static function plantStatusBadgeClass(string $status): string
    {
        return self::PLANT_STATUS_BADGES[$status] ?? 'bg-secondary';
    }

    public function isAtRisk(): bool
    {
        return in_array($this->status, [self::STATUS_UNHEALTHY, self::STATUS_DEAD], true);
    }
}
