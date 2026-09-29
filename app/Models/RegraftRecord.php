<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraftRecord extends Model
{
    use HasFactory;

    protected $table = 'regraft_records';

    protected $primaryKey = 'regraft_id';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'parent_batch_id',
        'user_id',
        'quantity',
        'regraft_date',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'regraft_date' => 'date',
        ];
    }

    /**
     * The batch this record is attached to — the regraft sub-batch it created
     * (Phase 7B: `batch_id` = the new sub-batch).
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    /**
     * Alias of {@see batch()}: the regraft sub-batch this record created.
     */
    public function subBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    /**
     * The source (parent) batch a regraft was taken from, when set (ERD §3.11).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'parent_batch_id', 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total quantity recorded against a batch id through `batch_id`
     * (RULE 6: single definition shared by the listing and analytics).
     */
    public static function totalForBatch(int $batchId): int
    {
        return (int) static::query()->where('batch_id', $batchId)->sum('quantity');
    }

    /**
     * Total quantity regrafted OUT of a source batch — SUM of the records whose
     * `parent_batch_id` is that batch. Used to reconcile the cached
     * `batches.regraft` counter in tests (Phase 7B).
     */
    public static function totalRegraftedFromBatch(int $batchId): int
    {
        return (int) static::query()->where('parent_batch_id', $batchId)->sum('quantity');
    }

    /**
     * Total regrafted quantity across a set of batches (RULE 6: same definition
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
