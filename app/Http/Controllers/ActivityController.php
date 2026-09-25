<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /**
     * Readable historical record of operational activities (FR-17 / PRD §24).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['nullable', 'string', Rule::in(array_keys(ActivityLog::TYPES))],
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = ActivityLog::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query
                ->where('user_id', $userId))
            ->when($filters['activity_type'] ?? null, fn ($query, $type) => $query
                ->where('activity_type', $type))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query
                ->where('batch_id', $batchId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('activity_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('activity_date', '<=', $dateTo));

        $activities = (clone $base)
            ->with(['user', 'batch.variety'])
            ->orderByDesc('activity_date')
            ->orderByDesc('activity_id')
            ->paginate(10)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'users' => User::orderBy('name')->get(),
            'batches' => Batch::orderBy('batch_code')->get(),
            'filters' => $filters,
            'totalActivities' => (clone $base)->count(),
            'usersInvolved' => (clone $base)->distinct()->count('user_id'),
            'batchesAffected' => (clone $base)->whereNotNull('batch_id')->distinct()->count('batch_id'),
            'activityTypes' => (clone $base)->distinct()->count('activity_type'),
        ]);
    }
}
