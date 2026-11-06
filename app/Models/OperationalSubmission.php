<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * PROPOSED Phase 2 operational-submission staging row.
 *
 * This model is intentionally unreferenced by any route, controller, view or
 * existing model. It applies no official operational effects; Phase 3 will own
 * approval application. Only the three statuses below are defined.
 *
 * A submission envelope is immutable: payload, submitter, type, submitted_at
 * and resubmission_of_id are frozen at creation. Status may only move from
 * pending to approved or rejected, exactly once. Decision methods open a
 * transaction, lock the row with lockForUpdate(), recheck that it is still
 * pending and then save.
 *
 * Raw SQL or DB::table() writes bypass these Eloquent guards. No application
 * code outside this model (and OperationalSubmissionRecord for its link rows)
 * writes to these tables in Phase 2.
 */
class OperationalSubmission extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public const TYPE_MORTALITY = 'mortality';

    public const TYPE_STOCK_OUT = 'stock_out';

    public const TYPE_STOCK_IN = 'stock_in';

    /**
     * Supported submission types. More types may be added later; values are
     * validated in code, never with a database ENUM.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_MORTALITY,
        self::TYPE_STOCK_OUT,
        self::TYPE_STOCK_IN,
    ];

    protected $table = 'operational_submissions';

    protected $primaryKey = 'submission_id';

    protected $fillable = [
        'submission_type',
        'status',
        'payload',
        'submitted_by_id',
        'submitted_at',
        'decided_by_id',
        'decided_at',
        'decision_reason',
        'decision_automatic',
        'resubmission_of_id',
        'subject_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'decision_automatic' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            $submission->guardCreation();
        });

        static::updating(function (self $submission): void {
            $submission->guardUpdate();
        });

        static::deleting(function (): void {
            throw new DomainException('Operational submissions cannot be deleted.');
        });
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmission_of_id', 'submission_id');
    }

    public function resubmissions(): HasMany
    {
        return $this->hasMany(self::class, 'resubmission_of_id', 'submission_id');
    }

    public function subjectBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'subject_batch_id', 'batch_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(OperationalSubmissionRecord::class, 'submission_id', 'submission_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Human-readable label for the submission type, used by the index, queue
     * and review screens and by the audit log.
     */
    public function typeLabel(): string
    {
        return match ($this->submission_type) {
            self::TYPE_MORTALITY => 'Mortality',
            self::TYPE_STOCK_OUT => 'Stock Out',
            self::TYPE_STOCK_IN => 'Stock In',
            default => ucfirst(str_replace('_', ' ', $this->submission_type)),
        };
    }

    /**
     * Short subject summary for a grouped Stock In submission.
     */
    /**
     * Whether a pending Stock In submission references this scion variety.
     * Used by the master-data deletion guard while the request is awaiting
     * verification, before any official stock_ins row exists.
     */
    public static function hasPendingStockInScionVariety(int $scionVarietyId): bool
    {
        return static::query()
            ->where('submission_type', self::TYPE_STOCK_IN)
            ->where('status', self::STATUS_PENDING)
            ->where('payload->scion->scion_variety_id', $scionVarietyId)
            ->exists();
    }

    public function stockInSummary(): string
    {
        $parts = [];

        $seedling = $this->payload['seedling'] ?? null;

        if (is_array($seedling)) {
            $parts[] = 'Seedlings '.number_format((int) ($seedling['quantity'] ?? 0));
        }

        $scion = $this->payload['scion'] ?? null;

        if (is_array($scion)) {
            $parts[] = 'Scion '.number_format((int) ($scion['quantity'] ?? 0));
        }

        return $parts === [] ? 'Stock In' : implode(' + ', $parts);
    }

    /**
     * Approve a pending submission. The decider must differ from the submitter.
     */
    public function approve(int $deciderId, ?string $note = null): self
    {
        return $this->decide($deciderId, self::STATUS_APPROVED, $note);
    }

    /**
     * Reject a pending submission with a written reason. `$automatic` marks the
     * D6 approval-time revalidation failure path; the reason must still name the
     * failed rule.
     */
    public function reject(int $deciderId, string $reason, bool $automatic = false): self
    {
        return $this->decide($deciderId, self::STATUS_REJECTED, $reason, $automatic);
    }

    /**
     * Internal decision entry point for Phase 3. It performs no official
     * effects. The caller may already be inside an outer transaction; Laravel
     * uses a savepoint for this nested transaction.
     */
    public function decide(int $deciderId, string $decision, ?string $reason = null, bool $automatic = false): self
    {
        if (! in_array($decision, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            throw new DomainException('Unsupported submission decision.');
        }

        return DB::transaction(function () use ($deciderId, $decision, $reason, $automatic): self {
            /** @var self $locked */
            $locked = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== self::STATUS_PENDING) {
                throw new DomainException('This submission has already been decided.');
            }

            if ((int) $locked->submitted_by_id === $deciderId) {
                throw new DomainException('The decider must differ from the submitter.');
            }

            $locked->status = $decision;
            $locked->decided_by_id = $deciderId;
            $locked->decided_at = now();
            $locked->decision_reason = $reason;
            $locked->decision_automatic = $automatic;
            $locked->save();

            $this->setRawAttributes($locked->getAttributes(), true);

            return $this;
        });
    }

    private function guardCreation(): void
    {
        if (! in_array($this->submission_type, self::TYPES, true)) {
            throw new DomainException('Unsupported submission type.');
        }

        if ($this->status === null) {
            $this->status = self::STATUS_PENDING;
        }

        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException('A new submission must be pending.');
        }

        if (! is_array($this->payload)) {
            throw new DomainException('The submission payload must be an array.');
        }

        if ($this->submitted_at === null) {
            throw new DomainException('A submission requires a submitted_at timestamp.');
        }

        if ($this->decided_by_id !== null
            || $this->decided_at !== null
            || $this->decision_reason !== null
            || (bool) $this->decision_automatic) {
            throw new DomainException('A new submission cannot carry a decision.');
        }

        if ($this->resubmission_of_id !== null) {
            $this->guardResubmissionReference();
        }
    }

    private function guardUpdate(): void
    {
        if ($this->isDirty(['payload', 'submitted_by_id', 'submission_type', 'submitted_at', 'resubmission_of_id'])) {
            throw new DomainException('Submission identity, payload and timestamps are immutable.');
        }

        $currentStatus = static::query()->whereKey($this->getKey())->value('status');

        if ($currentStatus !== self::STATUS_PENDING) {
            throw new DomainException('This submission has already been decided.');
        }

        $statusChanged = $this->isDirty('status');
        $decisionChanged = $this->isDirty(['decided_by_id', 'decided_at', 'decision_reason', 'decision_automatic']);

        if (! $statusChanged) {
            if ($decisionChanged) {
                throw new DomainException('Decision fields cannot change without deciding the submission.');
            }

            return;
        }

        if (! in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            throw new DomainException('A submission can only be approved or rejected.');
        }

        if ($this->decided_by_id === null) {
            throw new DomainException('A decision requires a decider.');
        }

        if ((int) $this->decided_by_id === (int) $this->submitted_by_id) {
            throw new DomainException('The decider must differ from the submitter.');
        }

        if ($this->decided_at === null) {
            throw new DomainException('A decision requires a decided_at timestamp.');
        }

        if ($this->status === self::STATUS_REJECTED && trim((string) $this->decision_reason) === '') {
            throw new DomainException('A rejection requires a written reason.');
        }

        if ($this->status === self::STATUS_APPROVED && (bool) $this->decision_automatic) {
            throw new DomainException('An approval cannot be automatic.');
        }
    }

    private function guardResubmissionReference(): void
    {
        $previous = static::query()->whereKey($this->resubmission_of_id)->first();

        if ($previous === null) {
            throw new DomainException('The resubmitted submission does not exist.');
        }

        if ($previous->status !== self::STATUS_REJECTED) {
            throw new DomainException('A submission may only be resubmitted from a rejected submission.');
        }

        if ($previous->submission_type !== $this->submission_type) {
            throw new DomainException('A resubmission must keep the original submission type.');
        }

        if ((int) $previous->submitted_by_id !== (int) $this->submitted_by_id) {
            throw new DomainException('Only the original submitter may resubmit.');
        }
    }
}
