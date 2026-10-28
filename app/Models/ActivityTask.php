<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTask extends Model
{
    protected $table = 'activity_tasks';

    protected $primaryKey = 'task_id';

    public $timestamps = false;

    /**
     * Priorities accepted by the checklist (approved ERD revision — ERD §2.4/§9.6).
     *
     * @var list<string>
     */
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'task_name',
        'description',
        'priority',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function completions(): HasMany
    {
        return $this->hasMany(TaskComplete::class, 'task_id', 'task_id');
    }

    /**
     * Archived tasks are hidden from the active checklist but keep their
     * completion history (archive/restore replaces hard deletion).
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Whether the task has any Task_Complete history. A task with history must
     * never be hard-deleted (that history is the audit trail — ERD §2.4/§9.6).
     */
    public function hasCompletions(): bool
    {
        if (array_key_exists('completions_count', $this->attributes)) {
            return (int) $this->attributes['completions_count'] > 0;
        }

        return $this->completions()->exists();
    }

    /**
     * Centralized delete rule (RULE 6): a task may only be permanently deleted
     * when it has no completion history. The Delete action replaces the former
     * archive/restore flow (owner-directed); a task with completion history
     * must be kept so the Task_Complete audit trail is never destroyed.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->hasCompletions();
    }

    /**
     * Scope a query to only active (non-archived) tasks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
