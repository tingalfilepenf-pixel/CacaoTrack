<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revised ERD entity `Inventory` (ERD §3.12).
 *
 * A **monthly snapshot** per batch (many rows per batch — the old per-batch
 * balance row is gone). The monthly counts and `status` are frozen into the row
 * when it is recorded; `status` is never derived on read (locked business
 * rule 2).
 */
class Inventory extends Model
{
    protected $table = 'inventories';

    protected $primaryKey = 'inventory_id';

    public $timestamps = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'batch_id',
        'inventory_date',
        'total_grafted',
        'viable',
        'regraft',
        'mortality',
        'status',
        'last_update',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date',
            'last_update' => 'datetime',
        ];
    }

    /**
     * Freeze the snapshot status at creation time from the batch's live
     * available balance (locked business rule 2). The status is a stored,
     * historical value — it is never re-derived when the row is read.
     */
    protected static function booted(): void
    {
        static::creating(function (self $inventory): void {
            if ($inventory->batch_id !== null) {
                $batch = Batch::query()->find($inventory->batch_id);

                if ($batch !== null) {
                    $inventory->status = self::statusForBatch($batch);
                }
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    /**
     * Inventory status definition (RULE 6: single definition shared by the
     * snapshot writer and the listing).
     *
     * active   = the batch still had an available balance at snapshot time
     * depleted = no available balance left at snapshot time
     */
    public static function statusForAvailable(int $availableCount): string
    {
        return $availableCount > 0 ? self::STATUS_ACTIVE : self::STATUS_DEPLETED;
    }

    /**
     * Status implied by a batch's live `available_count` — call this when a
     * monthly snapshot row is created (not in an accessor).
     */
    public static function statusForBatch(Batch $batch): string
    {
        return self::statusForAvailable((int) $batch->available_count);
    }
}
