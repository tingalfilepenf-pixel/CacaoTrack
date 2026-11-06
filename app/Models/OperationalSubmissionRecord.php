<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PROPOSED Phase 2 link row: one official record created by an approved
 * submission. A Stock Out writes its header plus one row per detail line.
 *
 * `record_id` deliberately has no database foreign key because the target table
 * depends on `record_type`; the (submission_id, record_type, record_id) and
 * (record_type, record_id) unique constraints prevent double attribution.
 *
 * Raw SQL or DB::table() writes bypass these Eloquent guards. No application
 * code outside this model (and OperationalSubmission for its envelope) writes
 * to these tables in Phase 2.
 */
class OperationalSubmissionRecord extends Model
{
    use HasFactory;

    public const RECORD_TYPE_MORTALITY = 'mortality_record';

    public const RECORD_TYPE_STOCK_OUT = 'stock_out';

    public const RECORD_TYPE_STOCK_OUT_DETAIL = 'stock_out_detail';

    public const RECORD_TYPE_STOCK_IN = 'stock_in';

    /**
     * @var list<string>
     */
    public const RECORD_TYPES = [
        self::RECORD_TYPE_MORTALITY,
        self::RECORD_TYPE_STOCK_OUT,
        self::RECORD_TYPE_STOCK_OUT_DETAIL,
        self::RECORD_TYPE_STOCK_IN,
    ];

    public const UPDATED_AT = null;

    protected $table = 'operational_submission_records';

    protected $primaryKey = 'submission_record_id';

    protected $fillable = [
        'submission_id',
        'record_type',
        'record_id',
    ];

    protected function casts(): array
    {
        return [
            'submission_id' => 'integer',
            'record_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! in_array($record->record_type, self::RECORD_TYPES, true)) {
                throw new DomainException('Unsupported official record type.');
            }

            if ((int) $record->record_id < 1) {
                throw new DomainException('The official record id must be positive.');
            }
        });

        static::updating(function (): void {
            throw new DomainException('Operational submission link rows are immutable.');
        });

        static::deleting(function (): void {
            throw new DomainException('Operational submission link rows cannot be deleted.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OperationalSubmission::class, 'submission_id', 'submission_id');
    }
}
