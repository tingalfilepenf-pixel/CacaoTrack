<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $primaryKey = 'activity_id';

    public $timestamps = false;

    /**
     * Recognised activity types (single source of truth for the type filter
     * and for every writer throughout the system — RULE 6 / PRD §24).
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'task_completed' => 'Task Completed',
        'task_archived' => 'Task Archived',
        'task_restored' => 'Task Restored',
        'task_deleted' => 'Task Deleted',
        'mortality_recorded' => 'Mortality Recorded',
        'mortality_adjusted' => 'Mortality Adjusted',
        'regraft_recorded' => 'Regraft Recorded',
        'stock_in_recorded' => 'Stock In Recorded',
        'stock_out_recorded' => 'Stock Out Recorded',
        'stock_reversal_recorded' => 'Stock Reversal Recorded',
        'report_generated' => 'Report Generated',
        'profile_updated' => 'Profile Updated',
        'user_created' => 'User Created',
        'user_updated' => 'User Updated',
        'user_deleted' => 'User Deleted',
        'batch_created' => 'Batch Created',
        'batch_updated' => 'Batch Updated',
        'batch_deleted' => 'Batch Deleted',
        'role_created' => 'Role Created',
        'role_updated' => 'Role Updated',
        'role_deleted' => 'Role Deleted',
        'supplier_archived' => 'Supplier Archived',
        'supplier_restored' => 'Supplier Restored',
        'client_archived' => 'Client Archived',
        'client_restored' => 'Client Restored',
        'variety_archived' => 'Variety Archived',
        'variety_restored' => 'Variety Restored',
        'scion_archived' => 'Scion Archived',
        'scion_restored' => 'Scion Restored',
        'scion_stock_received' => 'Scions Received',
        'scion_variety_archived' => 'Scion Variety Archived',
        'scion_variety_restored' => 'Scion Variety Restored',
        'pot_type_archived' => 'Pot Type Archived',
        'pot_type_restored' => 'Pot Type Restored',
        'batch_archived' => 'Batch Archived',
        'batch_restored' => 'Batch Restored',
        'batch_monitoring_started' => 'Monitoring Started',
        'batch_monitoring_recorded' => 'Monitoring Recorded',
    ];

    public const TYPE_TASK_COMPLETED = 'task_completed';

    public const TYPE_TASK_ARCHIVED = 'task_archived';

    public const TYPE_TASK_RESTORED = 'task_restored';

    public const TYPE_TASK_DELETED = 'task_deleted';

    public const TYPE_MORTALITY_RECORDED = 'mortality_recorded';

    public const TYPE_MORTALITY_ADJUSTED = 'mortality_adjusted';

    public const TYPE_REGRAFT_RECORDED = 'regraft_recorded';

    public const TYPE_STOCK_IN_RECORDED = 'stock_in_recorded';

    public const TYPE_STOCK_OUT_RECORDED = 'stock_out_recorded';

    public const TYPE_STOCK_REVERSAL_RECORDED = 'stock_reversal_recorded';

    public const TYPE_REPORT_GENERATED = 'report_generated';

    public const TYPE_PROFILE_UPDATED = 'profile_updated';

    public const TYPE_USER_CREATED = 'user_created';

    public const TYPE_USER_UPDATED = 'user_updated';

    public const TYPE_USER_DELETED = 'user_deleted';

    public const TYPE_BATCH_CREATED = 'batch_created';

    public const TYPE_BATCH_UPDATED = 'batch_updated';

    public const TYPE_BATCH_DELETED = 'batch_deleted';

    public const TYPE_ROLE_CREATED = 'role_created';

    public const TYPE_ROLE_UPDATED = 'role_updated';

    public const TYPE_ROLE_DELETED = 'role_deleted';

    public const TYPE_SUPPLIER_ARCHIVED = 'supplier_archived';

    public const TYPE_SUPPLIER_RESTORED = 'supplier_restored';

    public const TYPE_CLIENT_ARCHIVED = 'client_archived';

    public const TYPE_CLIENT_RESTORED = 'client_restored';

    public const TYPE_VARIETY_ARCHIVED = 'variety_archived';

    public const TYPE_VARIETY_RESTORED = 'variety_restored';

    public const TYPE_SCION_ARCHIVED = 'scion_archived';

    public const TYPE_SCION_RESTORED = 'scion_restored';

    public const TYPE_SCION_STOCK_RECEIVED = 'scion_stock_received';

    public const TYPE_SCION_VARIETY_ARCHIVED = 'scion_variety_archived';

    public const TYPE_SCION_VARIETY_RESTORED = 'scion_variety_restored';

    public const TYPE_POT_TYPE_ARCHIVED = 'pot_type_archived';

    public const TYPE_POT_TYPE_RESTORED = 'pot_type_restored';

    public const TYPE_BATCH_ARCHIVED = 'batch_archived';

    public const TYPE_BATCH_RESTORED = 'batch_restored';

    public const TYPE_BATCH_MONITORING_STARTED = 'batch_monitoring_started';

    public const TYPE_BATCH_MONITORING_RECORDED = 'batch_monitoring_recorded';

    /**
     * Badge class per activity type (presentation only).
     *
     * @var array<string, string>
     */
    private const TYPE_BADGES = [
        'task_completed' => 'bg-primary',
        'task_archived' => 'bg-secondary',
        'task_restored' => 'bg-success',
        'task_deleted' => 'bg-danger',
        'mortality_recorded' => 'bg-danger',
        'mortality_adjusted' => 'bg-warning text-dark',
        'regraft_recorded' => 'bg-success',
        'stock_in_recorded' => 'bg-info text-dark',
        'stock_out_recorded' => 'bg-secondary',
        'stock_reversal_recorded' => 'bg-dark',
        'report_generated' => 'bg-primary',
        'profile_updated' => 'bg-light text-dark border',
        'user_created' => 'bg-success',
        'user_updated' => 'bg-primary',
        'user_deleted' => 'bg-danger',
        'batch_created' => 'bg-success',
        'batch_updated' => 'bg-primary',
        'batch_deleted' => 'bg-danger',
        'role_created' => 'bg-success',
        'role_updated' => 'bg-primary',
        'role_deleted' => 'bg-danger',
        'supplier_archived' => 'bg-secondary',
        'supplier_restored' => 'bg-success',
        'client_archived' => 'bg-secondary',
        'client_restored' => 'bg-success',
        'variety_archived' => 'bg-secondary',
        'variety_restored' => 'bg-success',
        'scion_archived' => 'bg-secondary',
        'scion_restored' => 'bg-success',
        'scion_stock_received' => 'bg-info text-dark',
        'scion_variety_archived' => 'bg-secondary',
        'scion_variety_restored' => 'bg-success',
        'pot_type_archived' => 'bg-secondary',
        'pot_type_restored' => 'bg-success',
        'batch_archived' => 'bg-secondary',
        'batch_restored' => 'bg-success',
        'batch_monitoring_started' => 'bg-info text-dark',
        'batch_monitoring_recorded' => 'bg-primary',
    ];

    protected $fillable = [
        'user_id',
        'batch_id',
        'activity_type',
        'description',
        'activity_date',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->activity_type] ?? Str::headline($this->activity_type);
    }

    public function typeBadgeClass(): string
    {
        return self::badgeClassFor($this->activity_type);
    }

    /**
     * Centralized badge mapping for an activity type (presentation only).
     */
    public static function badgeClassFor(string $type): string
    {
        return self::TYPE_BADGES[$type] ?? 'bg-secondary';
    }

    /**
     * The single writer for activity history (RULE 6).
     *
     * Every operational feature records its traceable action through this
     * method rather than creating rows ad hoc (PRD §24). User-facing success
     * feedback is delivered separately as a transient session-flash toast;
     * this writer only persists the authoritative audit trail.
     *
     * Note: the backfill seeder writes rows with firstOrCreate() instead of
     * this method, so historic data is never duplicated.
     */
    public static function record(
        ?int $batchId,
        string $type,
        string $description,
        ?int $userId = null,
        mixed $activityDate = null,
    ): self {
        if (! array_key_exists($type, self::TYPES)) {
            throw new InvalidArgumentException("Unknown activity type [{$type}].");
        }

        $actorId = $userId ?? Auth::id();
        $summary = Str::limit($description, 255);

        return self::create([
            'user_id' => $actorId,
            'batch_id' => $batchId,
            'activity_type' => $type,
            'description' => $summary,
            'activity_date' => $activityDate ?? now(),
        ]);
    }
}
