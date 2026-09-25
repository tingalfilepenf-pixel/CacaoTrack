<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ActivityTask;
use App\Models\TaskComplete;
use App\Support\ChecklistPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DailyChecklistController extends Controller
{
    /**
     * Checklist listing. Active tasks are shown by default; `?status=archived`
     * lists archived tasks (which can be restored or, when history-free,
     * deleted). Archive/restore keeps the Task_Complete history intact
     * (ERD §2.4/§9.6); a guarded permanent delete is only offered for archived
     * tasks that have no completion history.
     */
    public function index(Request $request): View
    {
        $date = now()->toDateString();

        $status = $request->query('status') === 'archived' ? 'archived' : 'active';

        $priorityOrder = array_flip(ActivityTask::PRIORITIES);

        $tasks = match ($status) {
            'archived' => ActivityTask::withCount('completions')
                ->whereNotNull('archived_at')
                ->orderByDesc('archived_at')
                ->get(),
            default => ActivityTask::active()
                ->orderBy('task_id')
                ->get()
                ->sortBy(fn (ActivityTask $task) => $priorityOrder[$task->priority] ?? PHP_INT_MAX)
                ->values(),
        };

        $completions = TaskComplete::with(['user', 'verifier'])
            ->whereDate('completion_date', $date)
            ->get()
            ->keyBy('task_id');

        $completedCount = $tasks->filter(fn (ActivityTask $task) => $completions->has($task->task_id))->count();
        $pendingCount = $tasks->count() - $completedCount;

        return view('operational.checklist.index', [
            'tasks' => $tasks,
            'completions' => $completions,
            'date' => $date,
            'status' => $status,
            'completedCount' => $completedCount,
            'pendingCount' => $pendingCount,
            'archivedCount' => ActivityTask::whereNotNull('archived_at')->count(),
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        ActivityTask::create($this->validateTask($request));

        return redirect()
            ->route('operational.checklist')
            ->with('success', 'Task added successfully.');
    }

    public function updateTask(Request $request, ActivityTask $task): RedirectResponse
    {
        $task->update($this->validateTask($request));

        return redirect()
            ->route('operational.checklist')
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Archive a task instead of deleting it (archive/restore replaces hard
     * delete so completion history is never destroyed).
     */
    public function archiveTask(ActivityTask $task): RedirectResponse
    {
        if ($task->isArchived()) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'This task is already archived.');
        }

        DB::transaction(function () use ($task): void {
            $task->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_TASK_ARCHIVED,
                "Archived task: {$task->task_name}.",
            );
        });

        return redirect()
            ->route('operational.checklist')
            ->with('success', 'Task archived successfully.');
    }

    /**
     * Restore a previously archived task to the active checklist.
     */
    public function restoreTask(ActivityTask $task): RedirectResponse
    {
        if (! $task->isArchived()) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'This task is not archived.');
        }

        DB::transaction(function () use ($task): void {
            $task->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_TASK_RESTORED,
                "Restored task: {$task->task_name}.",
            );
        });

        return redirect()
            ->route('operational.checklist', ['status' => 'archived'])
            ->with('success', 'Task restored successfully.');
    }

    /**
     * Permanently delete an archived task — allowed only when the task has no
     * completion history, so the Task_Complete audit trail is never destroyed
     * (ERD §2.4/§9.6). Active tasks must be archived first, and a task with any
     * completion is refused (the `task_completes.task_id` FK is restrictOnDelete).
     */
    public function destroyTask(ActivityTask $task): RedirectResponse
    {
        $this->requireTaskDeleteAuthority();

        if (! $task->isArchived()) {
            return redirect()
                ->route('operational.checklist', ['status' => 'archived'])
                ->with('error', 'Only archived tasks can be deleted. Archive the task first.');
        }

        if ($task->hasCompletions()) {
            return redirect()
                ->route('operational.checklist', ['status' => 'archived'])
                ->with('error', 'This task has completion history and cannot be deleted. Keep it archived instead.');
        }

        $taskName = $task->task_name;

        DB::transaction(function () use ($task): void {
            $task->delete();

            ActivityLog::record(
                null,
                ActivityLog::TYPE_TASK_DELETED,
                "Deleted task: {$task->task_name}.",
            );
        });

        return redirect()
            ->route('operational.checklist', ['status' => 'archived'])
            ->with('success', "Task \"{$taskName}\" deleted permanently.");
    }

    public function completeTask(Request $request, ActivityTask $task): RedirectResponse
    {
        if ($task->isArchived()) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'Archived tasks cannot be completed. Restore the task first.');
        }

        $validated = $request->validate([
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = now()->toDateString();

        $alreadyCompleted = TaskComplete::where('task_id', $task->task_id)
            ->whereDate('completion_date', $date)
            ->exists();

        if ($alreadyCompleted) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'This task has already been completed today.');
        }

        DB::transaction(function () use ($task, $validated, $date): void {
            TaskComplete::create([
                'task_id' => $task->task_id,
                'user_id' => Auth::id(),
                'verified_by_id' => Auth::id(),
                'completion_date' => $date,
                'verified_at' => now(),
                'remark' => $validated['remark'] ?? null,
            ]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_TASK_COMPLETED,
                "Completed task: {$task->task_name}.",
                null,
                $date,
            );
        });

        return redirect()
            ->route('operational.checklist')
            ->with('success', 'Task marked as completed.');
    }

    /**
     * Printable Daily Activity Checklist (FPDF) built from real saved data
     * (Activity_Task + Task_Complete). Optionally filtered to a past date.
     */
    public function exportPdf(Request $request): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $dateCarbon = Carbon::parse($date);

        $completions = TaskComplete::with(['user', 'verifier'])
            ->whereDate('completion_date', $date)
            ->get()
            ->keyBy('task_id');

        $priorityOrder = array_flip(ActivityTask::PRIORITIES);

        // Tasks shown for the selected day: tasks that are active now plus any
        // archived task that was completed that day (so history is not lost).
        $tasks = ActivityTask::where(function ($query) use ($completions): void {
            $query->whereNull('archived_at');

            if ($completions->isNotEmpty()) {
                $query->orWhereIn('task_id', $completions->keys()->all());
            }
        })
            ->orderBy('task_id')
            ->get()
            ->sortBy(fn (ActivityTask $task) => $priorityOrder[$task->priority] ?? PHP_INT_MAX)
            ->values();

        $rows = $tasks->map(function (ActivityTask $task) use ($completions): array {
            $completion = $completions->get($task->task_id);

            return [
                'task_name' => $task->task_name,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => $completion ? 'Completed' : 'Pending',
                'completed_by' => $completion?->user?->name,
                'verified_by' => $completion?->verifier?->name,
                'remark' => $completion?->remark,
            ];
        })->all();

        $completedCount = $completions->count();
        $pendingCount = max(0, $tasks->count() - $completedCount);

        $pdf = new ChecklistPdf(
            $dateCarbon->format('F j, Y'),
            (string) $request->user()->name,
            now()->format('M j, Y g:i A'),
            $rows,
            $completedCount,
            $pendingCount,
        );

        $pdf->AddPage();
        $pdf->renderBody();

        $filename = 'daily-activity-checklist-'.$dateCarbon->format('Y-m-d').'.pdf';

        return response($pdf->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Server-side authorization (FR-03/PRD §21): only Managers and
     * Administrators may permanently delete a checklist task. This is a
     * role-based restriction centralized on `Role::CHECKLIST_DELETE_ROLES`
     * (RULE 6) — no permission key is invented (ERD §9.7).
     */
    private function requireTaskDeleteAuthority(): void
    {
        $user = request()->user();

        if (! $user || ! $user->canDeleteChecklistTasks()) {
            abort(403, 'Only Managers and Administrators may delete checklist tasks.');
        }
    }

    /**
     * @return array{task_name: string, description: string|null, priority: string}
     */
    private function validateTask(Request $request): array
    {
        return $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'string', Rule::in(ActivityTask::PRIORITIES)],
        ]);
    }
}
