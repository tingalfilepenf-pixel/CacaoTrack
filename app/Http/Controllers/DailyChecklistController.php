<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ActivityTask;
use App\Models\TaskComplete;
use App\Support\ChecklistHistory;
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
     * Checklist listing. Active tasks only (owner-directed: the Archived tab
     * was removed and archive/restore replaced by a guarded permanent Delete).
     * A task with completion history can never be deleted so the Task_Complete
     * audit trail is preserved (ERD §2.4/§9.6).
     *
     * The same page carries the read-only History tab (FR-03/FR-13): past
     * records are derived from the real `task_completes` rows through the
     * centralized {@see ChecklistHistory} support class — no new table, no
     * schema change, and nothing about a past record can be edited here.
     */
    public function index(Request $request): View
    {
        $date = now()->toDateString();

        $priorityOrder = array_flip(ActivityTask::PRIORITIES);

        $tasks = ActivityTask::active()
            ->withCount('completions')
            ->orderBy('task_id')
            ->get()
            ->sortBy(fn (ActivityTask $task) => $priorityOrder[$task->priority] ?? PHP_INT_MAX)
            ->values();

        $completions = TaskComplete::with(['user', 'verifier'])
            ->whereDate('completion_date', $date)
            ->get()
            ->keyBy('task_id');

        $completedCount = $tasks->filter(fn (ActivityTask $task) => $completions->has($task->task_id))->count();
        $pendingCount = $tasks->count() - $completedCount;

        $historyFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(ChecklistHistory::FILTER_STATUSES)],
            'history_date' => ['nullable', 'date'],
            'tab' => ['nullable', Rule::in(['today', 'history'])],
        ]);

        // Pagination links keep the active filters and tab but drop the
        // selected record, so every page starts from its own newest record.
        $historyRecords = ChecklistHistory::records($historyFilters)
            ->appends(collect($request->query())->except(['history_date', 'page'])->all());

        $selectedDate = $historyFilters['history_date'] ?? null;

        if ($selectedDate === null && $historyRecords->isNotEmpty()) {
            $selectedDate = $historyRecords->first()['date']->toDateString();
        }

        $historyRecord = $selectedDate !== null ? ChecklistHistory::record($selectedDate) : null;

        $activeTab = ($historyFilters['tab'] ?? null) === 'history' || ($historyFilters['history_date'] ?? null) !== null
            ? 'history'
            : 'today';

        return view('operational.checklist.index', [
            'tasks' => $tasks,
            'completions' => $completions,
            'date' => $date,
            'completedCount' => $completedCount,
            'pendingCount' => $pendingCount,
            'historyFilters' => $historyFilters,
            'historyRecords' => $historyRecords,
            'historyRecord' => $historyRecord,
            'selectedDate' => $selectedDate,
            'activeTab' => $activeTab,
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
     * Permanently delete a task — allowed only when the task has no completion
     * history, so the Task_Complete audit trail is never destroyed (ERD
     * §2.4/§9.6). A task with any completion is refused (the
     * `task_completes.task_id` FK is restrictOnDelete). Replaces the former
     * archive/restore flow (owner-directed).
     */
    public function destroyTask(ActivityTask $task): RedirectResponse
    {
        $this->requireTaskDeleteAuthority();

        if ($task->hasCompletions()) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'This task has completion history and cannot be deleted. Its records must be preserved.');
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
            ->route('operational.checklist')
            ->with('success', "Task \"{$taskName}\" deleted permanently.");
    }

    public function completeTask(Request $request, ActivityTask $task): RedirectResponse
    {
        if ($task->isArchived()) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'Archived tasks cannot be completed.');
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
            // Completion and verification are separate authorities: the Staff
            // member records the completion, and a Manager verifies it later
            // through verifyCompletion(). Self-verification is not recorded.
            TaskComplete::create([
                'task_id' => $task->task_id,
                'user_id' => Auth::id(),
                'verified_by_id' => null,
                'completion_date' => $date,
                'verified_at' => null,
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
     * Record a Manager's verification of a Staff completion using the existing
     * Task_Complete fields (`verified_by_id`, `verified_at`). Only holders of
     * `manage_checklist` can reach this route; Staff can never verify or change
     * a verification record. Verification happens after completion and cannot
     * be overwritten from the UI.
     */
    public function verifyCompletion(TaskComplete $completion): RedirectResponse
    {
        if ($completion->verified_at !== null) {
            return redirect()
                ->route('operational.checklist')
                ->with('error', 'This task has already been verified.');
        }

        $completion->update([
            'verified_by_id' => Auth::id(),
            'verified_at' => now(),
        ]);

        return redirect()
            ->route('operational.checklist')
            ->with('success', 'Task verification recorded.');
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

        // One shared definition of a day's checklist (RULE 6): the History tab
        // and this printable PDF read the exact same task list and completions.
        $completions = ChecklistHistory::completionsForDate($date);
        $tasks = ChecklistHistory::tasksForDate($date, $completions);

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
