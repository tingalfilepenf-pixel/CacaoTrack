<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplantingRecord extends Model
{
    use HasFactory;

    protected $table = 'replanting_records';

    protected $primaryKey = 'replanting_id';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'user_id',
        'quantity',
        'replanting_date',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'replanting_date' => 'date',
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
     * Total replanted quantity for a batch (RULE 6: single definition shared
     * by the listing, validation and analytics).
     */
    public static function totalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('quantity');
    }

    /**
     * Total replanted quantity across a set of batches (RULE 6: same definition
     * as totalForBatch, set-based for scion-level aggregation).
     *
     * @param  iterable<int, int|string>  $batchIds
     */
    public static function totalForBatches(iterable $batchIds): int
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return 0;
        }

        return (int) static::query()->whereIn('batch_id', $ids)->sum('quantity');
    }
}
