<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Revised ERD entity `Scion_Variety` (ERD §3.9).
 *
 * The single retained scion module: it replaces both the old `varieties`
 * master and the generic `scions` stock pool. A scion variety is used when a
 * batch is grafted/regrafted and is referenced by each Stock Out detail line.
 */
class ScionVariety extends Model
{
    use Archivable;
    use HasFactory;

    protected $table = 'scion_varieties';

    protected $primaryKey = 'scion_variety_id';

    public $timestamps = false;

    protected $fillable = [
        'scion_variety_name',
        'description',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'scion_variety_id', 'scion_variety_id');
    }

    public function stockOutDetails(): HasMany
    {
        return $this->hasMany(StockOutDetail::class, 'scion_variety_id', 'scion_variety_id');
    }

    /**
     * Stock In receipts that recorded this scion variety (a scion-inclusive
     * Stock_In row carries `scion_variety_id`, ERD §3.7).
     */
    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class, 'scion_variety_id', 'scion_variety_id');
    }

    /**
     * The single definition of a scion variety that may be used for a new
     * regraft: the variety is active AND at least one Stock In receipt recorded
     * it as scion. Seedling-only receipts leave `scion_variety_id` null and
     * therefore never qualify. No quantity, balance or freshness check applies.
     */
    public function scopeReceivedInStockIn(Builder $query): Builder
    {
        return $query->active()->whereHas('stockIns');
    }

    /**
     * Regraft records whose destination sub-batch uses this scion variety.
     * Each regraft is a replacement event that consumes scions of the chosen
     * variety.
     */
    public function regraftRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            RegraftRecord::class,
            Batch::class,
            'scion_variety_id',
            'batch_id',
            'scion_variety_id',
            'batch_id',
        );
    }

    /**
     * Eager-load the two figures behind {@see availableScionQuantity()} without
     * one query per variety in a regraft dropdown.
     */
    public function scopeWithScionAvailability(Builder $query): Builder
    {
        return $query
            ->withSum('stockIns as received_scion_quantity', 'quantity')
            ->withSum('regraftRecords as used_scion_quantity', 'quantity');
    }

    /**
     * Quantity received through scion Stock In receipts.
     */
    public function receivedScionQuantity(): int
    {
        return (int) ($this->received_scion_quantity ?? $this->stockIns()->sum('quantity'));
    }

    /**
     * Quantity already consumed by regrafts into sub-batches of this variety.
     */
    public function usedScionQuantity(): int
    {
        return (int) ($this->used_scion_quantity ?? $this->regraftRecords()->sum('quantity'));
    }

    /**
     * Signed remaining scion stock: received minus used. It is allowed to be
     * negative so correction validation can detect and reject a state where
     * more scions were consumed than received. Normal new-regraft validation
     * uses the clamped {@see availableScionQuantity()}.
     */
    public function scionBalance(): int
    {
        return $this->receivedScionQuantity() - $this->usedScionQuantity();
    }

    /**
     * Derived remaining scion stock: received minus used, never below zero.
     * This is computed from real records; no balance column is stored.
     */
    public function availableScionQuantity(): int
    {
        return max(0, $this->scionBalance());
    }

    /**
     * Lock the scion-variety rows that affect derived scion stock, one row at a
     * time, in ascending primary-key order. This deterministic order keeps
     * concurrent regrafts and corrections from deadlocking while serializing
     * every writer that can change received or consumed quantity.
     *
     * Call only inside a DB transaction. Read availability from the returned
     * (locked) models; never reuse a model loaded before the lock.
     *
     * @param  array<int|null>  $varietyIds
     * @return Collection<int, ScionVariety>
     */
    public static function lockForScionStock(array $varietyIds): Collection
    {
        $ids = collect($varietyIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $locked = collect();

        foreach ($ids as $id) {
            $variety = static::query()->lockForUpdate()->find($id);

            if ($variety !== null) {
                $locked->put((int) $variety->scion_variety_id, $variety);
            }
        }

        return $locked;
    }

    /**
     * Why this scion variety cannot be hard-deleted (empty = deletable).
     * References are restrictOnDelete, so a variety already used by a batch,
     * stock receipt or stock-out line is kept so operational history survives.
     *
     * @return list<string>
     */
    public function deletionBlockers(): array
    {
        $checks = [
            'batches' => 'batch assignments',
            'stockOutDetails' => 'stock-out lines',
            'stockIns' => 'stock-in receipts',
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
     * Centralized delete rule (RULE 6): a scion variety may only be deleted
     * when nothing references it.
     */
    public function canBeDeleted(): bool
    {
        return $this->deletionBlockers() === [];
    }

    /**
     * Centralized validation rules for Scion Variety master data (RULE 6).
     * Duplicate names are allowed (the ERD declares no unique constraint).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'scion_variety_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Display reference for the scion variety (presentation only, not a DB
     * column).
     */
    public function reference(): string
    {
        return 'SV-'.str_pad((string) $this->scion_variety_id, 3, '0', STR_PAD_LEFT);
    }
}
