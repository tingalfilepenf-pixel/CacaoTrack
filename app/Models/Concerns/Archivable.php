<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared archive/restore behavior for master-data entities
 * (approved ERD revision §9.8).
 *
 * A record with a NULL `archived_at` is active; a set value means it is
 * archived — hidden from the active listing and from new-record selectors
 * while all existing references keep resolving.
 */
trait Archivable
{
    /**
     * Whether this record is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope a query to only active (non-archived) records.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope a query to only archived records.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Centralized `exists` rule that only accepts an ACTIVE (non-archived)
     * record of this model's table. Used by the operational creation forms so
     * an archived master record cannot be referenced by a new record
     * (RULE 1/6, ERD §9.8).
     */
    public static function activeExistsRule(string $column): Exists
    {
        return Rule::exists((new static)->getTable(), $column)->whereNull('archived_at');
    }
}
