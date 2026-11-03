<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComplete extends Model
{
    protected $table = 'task_completes';

    protected $primaryKey = 'completion_id';

    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'verified_by_id',
        'completion_date',
        'verified_at',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Maker–checker rule (FR-03): a completion may be verified only while it
     * is still unverified and by a person other than the one who completed it.
     * This is the single definition reused by the controller guard and both
     * checklist views (RULE 6).
     */
    public function canBeVerifiedBy(User $user): bool
    {
        return $this->verified_at === null
            && (int) $this->user_id !== (int) $user->getKey();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ActivityTask::class, 'task_id', 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }
}
