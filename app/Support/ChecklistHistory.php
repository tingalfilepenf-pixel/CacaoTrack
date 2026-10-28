<?php

namespace App\Support;

use App\Models\ActivityTask;
use App\Models\TaskComplete;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only Daily Checklist history (FR-03/FR-13).
 *
 * One record per calendar day that has at least one stored Task_Complete row.
 * A record's task list uses the same definition as the printable checklist
 * (active tasks now plus any task completed that day, priority-ordered), so
 * the History tab, the "View Full Details" modal and the existing
 * `ChecklistPdf` can never disagree (RULE 6).
 *
 * Nothing is fabricated: every value comes from the real `activity_tasks` and
 * `task_completes` rows. A day without any recorded completion has no stored
 * record, so the reachable record statuses are Completed (every task on the
 * day's list is done) and Pending (some are still open); an individual open
 * task is reported as "Not Done" inside the record details.
 */
class ChecklistHistory
{
    /**
     * Display title of a daily checklist record (no title column exists in the
     * schema — ERD §3.4/§3.5).
     */
    public const RECORD_TITLE = 'Daily Checklist';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_NOT_DONE = 'not_done';

    /**
     * Record statuses a user can filter by. `not_done` is a per-task state only
     * — a record exists only when at least one completion was stored.
     *
     * @var list<string>
     */
    public const FILTER_STATUSES = [self::STATUS_COMPLETED, self::STATUS_PENDING];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_NOT_DONE => 'Not Done',
    ];

    /**
     * Paginated history records (newest first), one entry per completion date.
     *
     * @param  array{search?: string|null, date_from?: string|null, date_to?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, array{date: Carbon, status: string, completed_count: int, pending_count: int, total_count: int, staff: list<array{name: string, role: string}>, verifiers: list<array{name: string, role: string}>, checked_at: Carbon|null}>
     */
    public static function records(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $dates = TaskComplete::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->whereHas('user', fn (Builder $userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('task', fn (Builder $taskQuery) => $taskQuery
                            ->where('task_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query
                ->whereDate('completion_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query
                ->whereDate('completion_date', '<=', $dateTo))
            ->groupBy('completion_date')
            ->orderByDesc('completion_date')
            ->pluck('completion_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $completionsByDate = $dates->isEmpty()
            ? collect()
            : TaskComplete::with(['user:id,name,role', 'verifier:id,name,role'])
                ->whereIn('completion_date', $dates->all())
                ->get()
                ->groupBy(fn (TaskComplete $completion) => $completion->completion_date->toDateString());

        $activeTaskIds = ActivityTask::active()->pluck('task_id')->all();

        $records = $dates->map(function (string $date) use ($completionsByDate, $activeTaskIds): array {
            $completions = $completionsByDate->get($date, collect());
            $completedTaskIds = $completions->pluck('task_id')->unique()->values();
            $completedCount = $completedTaskIds->count();
            $totalCount = count(array_unique(array_merge($activeTaskIds, $completedTaskIds->all())));

            return [
                'date' => Carbon::parse($date),
                'status' => self::statusFor($completedCount, $totalCount),
                'completed_count' => $completedCount,
                'pending_count' => max(0, $totalCount - $completedCount),
                'total_count' => $totalCount,
                'staff' => self::people($completions->pluck('user')),
                'verifiers' => self::people($completions->pluck('verifier')),
                'checked_at' => $completions->pluck('verified_at')->filter()->max(),
            ];
        });

        if (($filters['status'] ?? null) !== null) {
            $records = $records->where('status', $filters['status'])->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Full read-only detail of one day's checklist, or null when the date has
     * no stored completion.
     *
     * @return array{date: Carbon, status: string, completed_count: int, pending_count: int, total_count: int, staff: list<array{name: string, role: string}>, verifiers: list<array{name: string, role: string}>, checked_at: Carbon|null, tasks: list<array{task: ActivityTask, status: string, completion_id: int|null, completed_by: string|null, verified: bool, verified_by: string|null, verified_at: Carbon|null, remark: string|null}>}|null
     */
    public static function record(string $date): ?array
    {
        $completions = self::completionsForDate($date);

        if ($completions->isEmpty()) {
            return null;
        }

        $tasks = self::tasksForDate($date, $completions);
        $completedCount = $completions->count();
        $totalCount = $tasks->count();

        return [
            'date' => Carbon::parse($date),
            'status' => self::statusFor($completedCount, $totalCount),
            'completed_count' => $completedCount,
            'pending_count' => max(0, $totalCount - $completedCount),
            'total_count' => $totalCount,
            'staff' => self::people($completions->pluck('user')),
            'verifiers' => self::people($completions->pluck('verifier')),
            'checked_at' => $completions->pluck('verified_at')->filter()->max(),
            'tasks' => $tasks->map(function (ActivityTask $task) use ($completions): array {
                $completion = $completions->get($task->task_id);

                return [
                    'task' => $task,
                    'status' => $completion === null ? self::STATUS_NOT_DONE : self::STATUS_COMPLETED,
                    'completion_id' => $completion?->completion_id,
                    'completed_by' => $completion?->user?->name,
                    'verified' => $completion?->verified_at !== null,
                    'verified_by' => $completion?->verifier?->name,
                    'verified_at' => $completion?->verified_at,
                    'remark' => $completion?->remark,
                ];
            })->all(),
        ];
    }

    /**
     * The stored completions for one date, keyed by task id (the unique index
     * is `[task_id, completion_date]`, so one completion per task per day).
     *
     * @return Collection<int, TaskComplete>
     */
    public static function completionsForDate(string $date): Collection
    {
        return TaskComplete::with(['user', 'verifier'])
            ->whereDate('completion_date', $date)
            ->get()
            ->keyBy('task_id');
    }

    /**
     * The task list a day's checklist is judged against: active tasks now plus
     * any task completed that day (so archived-task history is never lost),
     * priority-ordered — the single definition shared by the History tab and
     * `ChecklistPdf`.
     *
     * @param  Collection<int, TaskComplete>|null  $completions
     * @return Collection<int, ActivityTask>
     */
    public static function tasksForDate(string $date, ?Collection $completions = null): Collection
    {
        $completions ??= self::completionsForDate($date);
        $completedTaskIds = $completions->keys()->all();
        $priorityOrder = array_flip(ActivityTask::PRIORITIES);

        return ActivityTask::query()
            ->where(function (Builder $query) use ($completedTaskIds): void {
                $query->whereNull('archived_at');

                if ($completedTaskIds !== []) {
                    $query->orWhereIn('task_id', $completedTaskIds);
                }
            })
            ->orderBy('task_id')
            ->get()
            ->sortBy(fn (ActivityTask $task) => $priorityOrder[$task->priority] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Centralized record-status rule: a day is Completed only when every task
     * on its list was completed that day; otherwise Pending.
     */
    public static function statusFor(int $completedCount, int $totalCount): string
    {
        return $totalCount > 0 && $completedCount >= $totalCount
            ? self::STATUS_COMPLETED
            : self::STATUS_PENDING;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    /**
     * @param  Collection<int, User|null>  $users
     * @return list<array{name: string, role: string}>
     */
    private static function people(Collection $users): array
    {
        return $users
            ->filter()
            ->unique('id')
            ->map(fn (User $user) => ['name' => (string) $user->name, 'role' => (string) $user->role])
            ->values()
            ->all();
    }
}
