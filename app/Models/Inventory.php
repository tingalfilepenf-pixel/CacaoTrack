<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $primaryKey = 'inventory_id';

    public $timestamps = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'batch_id',
        'current_quantity',
        'status',
        'last_update',
    ];

    protected function casts(): array
    {
        return [
            'last_update' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    /**
     * Inventory status definition (RULE 6: single definition shared by the
     * listing, stock operations and analytics).
     *
     * active   = positive balance still available
     * depleted = no balance left to release
     */
    public static function statusForQuantity(int $quantity): string
    {
        return $quantity > 0 ? self::STATUS_ACTIVE : self::STATUS_DEPLETED;
    }

    /**
     * Status implied by the stored balance.
     */
    public function derivedStatus(): string
    {
        return self::statusForQuantity((int) $this->current_quantity);
    }

    /**
     * Total Active Inventory KPI: current quantity across active inventory
     * records (PRD §12.1). Defined once so dashboard, reports and this listing
     * all share the same formula (RULE 6).
     */
    public static function totalActiveQuantity(): int
    {
        return (int) static::query()
            ->where('status', self::STATUS_ACTIVE)
            ->sum('current_quantity');
    }

    /**
     * Apply a signed quantity change to the stored balance.
     *
     * Single writer used by stock operations (RULE 7). Callers must run this
     * inside a DB transaction and must not allow the balance to go negative.
     */
    public function applyDelta(int $delta): self
    {
        $newQuantity = (int) $this->current_quantity + $delta;

        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Inventory balance cannot become negative.');
        }

        $this->current_quantity = $newQuantity;
        $this->status = self::statusForQuantity($newQuantity);
        $this->last_update = now();
        $this->save();

        return $this;
    }

    /**
     * Initialize (or refresh) the inventory balance for a batch.
     *
     * The ERD keeps the balance on Inventory.Current_Quantity; it begins as the
     * batch's available count and is only changed by stock operations (RULE 7).
     */
    public static function syncFromBatch(Batch $batch, bool $overwriteQuantity = false): self
    {
        $inventory = static::firstOrNew(['batch_id' => $batch->batch_id]);

        if ($overwriteQuantity || ! $inventory->exists) {
            $inventory->current_quantity = (int) $batch->available_count;
        }

        $inventory->status = self::statusForQuantity((int) $inventory->current_quantity);
        $inventory->last_update = now();
        $inventory->save();

        return $inventory;
    }
}
