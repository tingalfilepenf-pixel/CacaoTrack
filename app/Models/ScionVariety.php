<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
