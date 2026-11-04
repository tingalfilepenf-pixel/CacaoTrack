<?php

namespace App\Support;

use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of who may see which submissions (U8).
 *
 * Administrator: all, read-only.
 * Manager / any verify_operations holder: all.
 * A non-Staff read-only holder: all.
 * Staff (and any other submit-only account): own submissions only.
 */
final class OperationalSubmissionVisibility
{
    public static function canViewAll(User $user): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->roleLabel() === Role::MANAGER || $user->hasPermission('verify_operations')) {
            return true;
        }

        return $user->hasPermission('view_operational_records') && $user->roleLabel() !== Role::STAFF;
    }

    /**
     * @param  Builder<OperationalSubmission>  $query
     * @return Builder<OperationalSubmission>
     */
    public static function scope(Builder $query, User $user): Builder
    {
        if (self::canViewAll($user)) {
            return $query;
        }

        return $query->where('submitted_by_id', $user->id);
    }

    public static function canView(User $user, OperationalSubmission $submission): bool
    {
        return self::canViewAll($user)
            || (int) $submission->submitted_by_id === (int) $user->id;
    }
}
