<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\StockIn;
use Illuminate\Support\Collection;

/**
 * The single definition of the derived seedling stock pool (Phase 4b).
 *
 * Seedlings available = received seedling-only Stock In rows minus the
 * quantities used by approved own-nursery batches. Nothing is stored as a
 * balance: pending/rejected submissions hold nothing, and legacy/direct batches
 * (used quantities null) are deliberately not deducted.
 *
 * Scion availability stays on {@see ScionVariety} because it is
 * per-variety and already has one definition shared by Regraft, corrections and
 * the forms.
 */
final class StockBalances
{
    /**
     * Seedlings received through seedling-only Stock In rows.
     */
    public static function seedlingsReceived(): int
    {
        return (int) StockIn::query()
            ->whereNull('scion_variety_id')
            ->sum('quantity');
    }

    /**
     * Seedlings consumed by approved own-nursery batches.
     */
    public static function seedlingsUsed(): int
    {
        return (int) Batch::query()
            ->whereNotNull('seedlings_used')
            ->sum('seedlings_used');
    }

    /**
     * Signed seedling balance: received minus used. Negative values are useful
     * to correction validation; forms and approval use the clamped value.
     */
    public static function seedlingsBalance(?int $received = null, ?int $used = null): int
    {
        return ($received ?? self::seedlingsReceived()) - ($used ?? self::seedlingsUsed());
    }

    /**
     * Non-negative seedling balance for new batch submissions and approval.
     */
    public static function seedlingsAvailable(?int $received = null, ?int $used = null): int
    {
        return max(0, self::seedlingsBalance($received, $used));
    }

    /**
     * Lock every seedling-only Stock In row in ascending id order. There is no
     * single balance row, so this is the deterministic pool lock shared by
     * approval and seedling-affecting Stock In corrections.
     *
     * Call only inside a DB transaction.
     *
     * @return Collection<int, StockIn>
     */
    public static function lockSeedlingPool(): Collection
    {
        return StockIn::query()
            ->whereNull('scion_variety_id')
            ->orderBy('stock_in_id')
            ->lockForUpdate()
            ->get();
    }
}
