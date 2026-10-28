@extends('layouts.app')

@section('title', 'Daily Checklist')
@section('page_title', 'Daily Checklist')

@section('page_actions')
<button class="btn btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#exportPdfModal">
    <i class="bi bi-printer me-1"></i> Print Checklist
</button>
@if (auth()->user()->hasPermission('manage_checklist'))
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="bi bi-plus-lg me-1"></i> Add Task
    </button>
@endif
@endsection

@section('content')
@php
    $priorityBadges = [
        'high' => ['label' => 'High', 'class' => 'bg-danger'],
        'medium' => ['label' => 'Medium', 'class' => 'bg-warning text-dark'],
        'low' => ['label' => 'Low', 'class' => 'bg-secondary'],
    ];

    // Record + per-task status badge colors (labels come from the centralized
    // ChecklistHistory catalog — RULE 6).
    $historyStatusBadges = [
        \App\Support\ChecklistHistory::STATUS_COMPLETED => 'bg-success',
        \App\Support\ChecklistHistory::STATUS_PENDING => 'bg-warning text-dark',
        \App\Support\ChecklistHistory::STATUS_NOT_DONE => 'bg-danger',
    ];

    $memberNames = fn (array $people) => collect($people)->pluck('name')->join(', ');
    $memberRoles = fn (array $people) => collect($people)->pluck('role')->unique()->join(', ');
    $isHistory = $activeTab === 'history';

    // Two distinct checklist authorities: completing a task is not the same as
    // managing or verifying the task list.
    $canManageChecklist = auth()->user()->hasPermission('manage_checklist');
    $canCompleteChecklist = auth()->user()->hasPermission('complete_checklist');
    $canDeleteChecklist = $canManageChecklist && auth()->user()->canDeleteChecklistTasks();
@endphp

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<ul class="nav nav-pills checklist-tabs mb-4" id="checklistTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $isHistory ? '' : 'active' }}" id="todayTab" data-bs-toggle="tab" data-bs-target="#todayPane"
            type="button" role="tab" aria-controls="todayPane" aria-selected="{{ $isHistory ? 'false' : 'true' }}">
            <i class="bi bi-clipboard-check me-1"></i> Today's Checklist
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $isHistory ? 'active' : '' }}" id="historyTab" data-bs-toggle="tab" data-bs-target="#historyPane"
            type="button" role="tab" aria-controls="historyPane" aria-selected="{{ $isHistory ? 'true' : 'false' }}">
            <i class="bi bi-clock-history me-1"></i> History
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Today's Checklist -->
    <div class="tab-pane fade {{ $isHistory ? '' : 'show active' }}" id="todayPane" role="tabpanel" aria-labelledby="todayTab">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-calendar3 me-2"></i>Date: {{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}
                </h6>
                <div>
                    <span class="badge bg-success me-2">{{ $completedCount }} Completed</span>
                    <span class="badge bg-warning text-dark">{{ $pendingCount }} Pending</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">TASK</th>
                                <th>PRIORITY</th>
                                <th>STATUS</th>
                                <th class="text-end pe-4">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tasks as $task)
                                @php
                                    $completion = $completions->get($task->task_id);
                                    $badge = $priorityBadges[$task->priority] ?? ['label' => ucfirst($task->priority), 'class' => 'bg-secondary'];
                                @endphp
                                <tr>
                                    <td class="ps-4 fw-medium">
                                        {{ $task->task_name }}
                                        @if ($task->description)
                                            <div class="text-muted small fw-normal">{{ $task->description }}</div>
                                        @endif
                                        @if ($completion)
                                            <div class="text-muted small fw-normal">
                                                Completed {{ $completion->completion_date?->format('M j, Y') }}
                                                by {{ $completion->user?->name ?? 'Unknown user' }}
                                                @if ($completion->remark)
                                                    &mdash; &ldquo;{{ $completion->remark }}&rdquo;
                                                @endif
                                            </div>
                                            @if ($completion->verified_at)
                                                <div class="text-success small fw-normal">
                                                    <i class="bi bi-patch-check me-1"></i>Verified by {{ $completion->verifier?->name ?? 'a Manager' }}
                                                    on {{ $completion->verified_at->format('M j, Y g:i A') }}
                                                </div>
                                            @else
                                                <div class="text-warning-emphasis small fw-normal">
                                                    <i class="bi bi-hourglass-split me-1"></i>Awaiting Manager verification
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                                    <td>
                                        @if ($completion)
                                            <span class="badge bg-success">Completed</span>
                                            @if ($completion->verified_at)
                                                <span class="badge bg-primary ms-1">Verified</span>
                                            @else
                                                <span class="badge bg-warning text-dark ms-1">Unverified</span>
                                            @endif
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        @unless ($completion)
                                            @if ($canCompleteChecklist)
                                                <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#completeTaskModal"
                                                    data-complete-url="{{ route('operational.checklist.tasks.complete', $task) }}"
                                                    data-task-name="{{ $task->task_name }}" title="Mark Complete">
                                                    <i class="bi bi-check2-square"></i>
                                                </button>
                                            @endif
                                        @else
                                            @if (! $completion->verified_at && $canManageChecklist)
                                                <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#verifyTaskModal"
                                                    data-verify-url="{{ route('operational.checklist.completions.verify', $completion) }}"
                                                    data-task-name="{{ $task->task_name }}" title="Verify completion">
                                                    <i class="bi bi-patch-check"></i>
                                                </button>
                                            @endif
                                        @endunless
                                        @if ($canManageChecklist)
                                            <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                                data-update-url="{{ route('operational.checklist.tasks.update', $task) }}"
                                                data-task-name="{{ $task->task_name }}"
                                                data-task-description="{{ $task->description }}"
                                                data-task-priority="{{ $task->priority }}" title="View/Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            @if ($canDeleteChecklist)
                                                @if ($task->canBeDeleted())
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTaskModal"
                                                        data-delete-url="{{ route('operational.checklist.tasks.destroy', $task) }}"
                                                        data-task-name="{{ $task->task_name }}" title="Delete permanently">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                        title="This task has completion history and cannot be deleted.">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>
                                        No tasks yet. Use &ldquo;Add Task&rdquo; to create the first checklist item.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- History (read-only records from Activity_Task / Task_Complete) -->
    <div class="tab-pane fade {{ $isHistory ? 'show active' : '' }}" id="historyPane" role="tabpanel" aria-labelledby="historyTab">
        <p class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Past checklist records come from the saved Activity_Task and Task_Complete data.
            A record exists for every day with at least one recorded task completion.
            Managers can verify a completion here; task details stay read-only.
        </p>

        <!-- History Filters (auto-apply server-side) -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('operational.checklist') }}" class="row g-3 align-items-end" id="historyFilterForm" data-auto-filter>
                    <input type="hidden" name="tab" value="history">
                    <div class="col-12 col-md-4">
                        <label for="historySearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="historySearch" name="search" value="{{ $historyFilters['search'] ?? '' }}" placeholder="Search by staff or task...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="historyDateFrom" class="form-label text-muted small fw-bold mb-1">From</label>
                        <input type="date" class="form-control form-control-sm" id="historyDateFrom" name="date_from" value="{{ $historyFilters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="historyDateTo" class="form-label text-muted small fw-bold mb-1">To</label>
                        <input type="date" class="form-control form-control-sm" id="historyDateTo" name="date_to" value="{{ $historyFilters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="historyStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                        <select class="form-select form-select-sm" id="historyStatus" name="status">
                            <option value="">All Statuses</option>
                            <option value="completed" @selected(($historyFilters['status'] ?? null) === 'completed')>Completed</option>
                            <option value="pending" @selected(($historyFilters['status'] ?? null) === 'pending')>Pending</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <a href="{{ route('operational.checklist', ['tab' => 'history']) }}" class="btn btn-sm btn-outline-secondary w-100" title="Clear all filters">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <!-- Record list -->
            <div class="col-12 col-lg-5">
                <div class="card h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-list-ul me-2"></i>Checklist Records</h6>
                        <span class="text-muted small">{{ $historyRecords->total() }} {{ \Illuminate\Support\Str::plural('record', $historyRecords->total()) }}</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($historyRecords->isEmpty())
                            <div class="text-center text-muted py-5 px-4">
                                <i class="bi bi-clock-history fs-3 d-block mb-2"></i>
                                No checklist records found for the current filters.
                            </div>
                        @else
                            <div class="list-group list-group-flush" id="historyRecordList">
                                @foreach ($historyRecords as $record)
                                    @php
                                        $recordDate = $record['date']->toDateString();
                                        $isSelected = $selectedDate === $recordDate;
                                        $recordBadge = $historyStatusBadges[$record['status']] ?? 'bg-secondary';
                                    @endphp
                                    <a href="{{ route('operational.checklist', array_merge(request()->query(), ['tab' => 'history', 'history_date' => $recordDate])) }}"
                                        class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 history-record {{ $isSelected ? 'active-record' : '' }}"
                                        @if ($isSelected) aria-current="true" @endif>
                                        <span class="history-record-icon"><i class="bi bi-calendar-check"></i></span>
                                        <span class="flex-grow-1 record-meta">
                                            <span class="d-block fw-semibold">{{ $record['date']->format('M j, Y') }}</span>
                                            <span class="d-block text-muted small">{{ \App\Support\ChecklistHistory::RECORD_TITLE }} &middot; {{ $record['completed_count'] }}/{{ $record['total_count'] }} tasks completed</span>
                                            <span class="d-block text-muted small text-truncate"><i class="bi bi-person me-1"></i>Staff: {{ $memberNames($record['staff']) ?: 'Unknown user' }}</span>
                                        </span>
                                        <span class="d-flex align-items-center gap-2 flex-shrink-0">
                                            <span class="badge {{ $recordBadge }}">{{ \App\Support\ChecklistHistory::statusLabel($record['status']) }}</span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($historyRecords->hasPages())
                        <div class="card-footer bg-white py-3">
                            {{ $historyRecords->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Selected record detail -->
            <div class="col-12 col-lg-7">
                @if ($historyRecord)
                    <div class="card h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-start gap-3">
                            <div class="d-flex align-items-center gap-3 record-meta">
                                <span class="history-detail-icon"><i class="bi bi-calendar-check"></i></span>
                                <div>
                                    <h5 class="mb-0">{{ \App\Support\ChecklistHistory::RECORD_TITLE }}</h5>
                                    <div class="text-muted small">{{ $historyRecord['date']->format('F j, Y') }}</div>
                                </div>
                            </div>
                            <span class="badge {{ $historyStatusBadges[$historyRecord['status']] ?? 'bg-secondary' }}">{{ \App\Support\ChecklistHistory::statusLabel($historyRecord['status']) }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-md-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-person text-success mt-1"></i>
                                        <div class="record-meta">
                                            <div class="text-muted small fw-bold text-uppercase">Staff</div>
                                            <div>{{ $memberNames($historyRecord['staff']) ?: 'Unknown user' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-patch-check text-success mt-1"></i>
                                        <div class="record-meta">
                                            <div class="text-muted small fw-bold text-uppercase">Verified By</div>
                                            <div>{{ $memberNames($historyRecord['verifiers']) ?: 'Not verified' }}</div>
                                            @if ($memberNames($historyRecord['verifiers']))
                                                <div class="text-muted small">({{ $memberRoles($historyRecord['verifiers']) }})</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-clock text-success mt-1"></i>
                                        <div class="record-meta">
                                            <div class="text-muted small fw-bold text-uppercase">Time Checked</div>
                                            <div>{{ $historyRecord['checked_at']?->format('g:i A') ?? '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-list-check me-2"></i>Checklist Tasks</h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3" style="width: 3rem;">#</th>
                                            <th>TASK</th>
                                            <th>STATUS</th>
                                            <th>REMARKS</th>
                                            @if ($canManageChecklist)
                                                <th class="text-end pe-3">VERIFICATION</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($historyRecord['tasks'] as $index => $row)
                                            <tr>
                                                <td class="ps-3 text-muted">{{ $index + 1 }}</td>
                                                <td>
                                                    <div class="fw-medium">{{ $row['task']->task_name }}</div>
                                                    @if ($row['task']->description)
                                                        <div class="text-muted small">{{ $row['task']->description }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge {{ $historyStatusBadges[$row['status']] ?? 'bg-secondary' }}">{{ \App\Support\ChecklistHistory::statusLabel($row['status']) }}</span>
                                                </td>
                                                <td class="text-muted">{{ $row['remark'] ?: '—' }}</td>
                                                @if ($canManageChecklist)
                                                    <td class="text-end pe-3">
                                                        @if ($row['status'] === \App\Support\ChecklistHistory::STATUS_COMPLETED)
                                                            @if ($row['verified'])
                                                                <span class="badge bg-primary">Verified</span>
                                                                @if ($row['verified_by'])
                                                                    <div class="text-muted small">{{ $row['verified_by'] }}</div>
                                                                @endif
                                                            @else
                                                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#verifyTaskModal"
                                                                    data-verify-url="{{ route('operational.checklist.completions.verify', $row['completion_id']) }}"
                                                                    data-task-name="{{ $row['task']->task_name }}" title="Verify completion">
                                                                    <i class="bi bi-patch-check me-1"></i>Verify
                                                                </button>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-muted small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>{{ $historyRecord['completed_count'] }} of {{ $historyRecord['total_count'] }} tasks completed on this date.
                                Completed task details are read-only; a Manager can still record verification.
                            </p>
                        </div>
                        <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
                            <a href="{{ route('operational.checklist.export', ['date' => $historyRecord['date']->toDateString()]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                                <i class="bi bi-printer me-1"></i> Print
                            </a>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#historyDetailsModal">
                                <i class="bi bi-eye me-1"></i> View Full Details
                            </button>
                        </div>
                    </div>
                @else
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                            <i class="bi bi-clock-history fs-2 text-muted mb-2"></i>
                            <p class="text-muted mb-0">Select a record to review its tasks, remarks and verification status.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($canCompleteChecklist)
<!-- Complete Task Modal -->
<div class="modal fade" id="completeTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="completeTaskForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Complete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Task</label>
                        <input type="text" class="form-control" id="completeTaskName" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="completeTaskRemark" class="form-label text-muted small fw-bold">Remarks</label>
                        <textarea class="form-control" id="completeTaskRemark" name="remark" rows="3" maxlength="1000" placeholder="Enter any observations or remarks..."></textarea>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Your account is recorded as the staff member who completed this task. A Manager will verify it afterwards.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Complete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canManageChecklist)
<!-- Verify Task Modal -->
<div class="modal fade" id="verifyTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="verifyTaskForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Verify Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Task</label>
                        <input type="text" class="form-control" id="verifyTaskName" readonly>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-patch-check me-1"></i>Recording verification saves your account as the verifier with the current date and time.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canDeleteChecklist)
<!-- Delete Task Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteTaskForm">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title">Delete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Permanently delete <strong id="deleteTaskName"></strong>?
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>This cannot be undone. A task with completion
                        history cannot be deleted, so its records are preserved.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canManageChecklist)
<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('operational.checklist.tasks.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addTaskName" class="form-label text-muted small fw-bold">Task Name</label>
                        <input type="text" class="form-control" id="addTaskName" name="task_name" value="{{ old('task_name') }}" maxlength="255" placeholder="e.g. Apply fertilizer" required>
                    </div>
                    <div class="mb-3">
                        <label for="addTaskDescription" class="form-label text-muted small fw-bold">Description</label>
                        <textarea class="form-control" id="addTaskDescription" name="description" rows="2" maxlength="1000" placeholder="Optional details...">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addTaskPriority" class="form-label text-muted small fw-bold">Priority</label>
                        <select class="form-select" id="addTaskPriority" name="priority">
                            <option value="high" @selected(old('priority') === 'high')>High</option>
                            <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium</option>
                            <option value="low" @selected(old('priority') === 'low')>Low</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editTaskForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTaskName" class="form-label text-muted small fw-bold">Task Name</label>
                        <input type="text" class="form-control" id="editTaskName" name="task_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskDescription" class="form-label text-muted small fw-bold">Description</label>
                        <textarea class="form-control" id="editTaskDescription" name="description" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskPriority" class="form-label text-muted small fw-bold">Priority</label>
                        <select class="form-select" id="editTaskPriority" name="priority">
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Export PDF Modal -->
<div class="modal fade" id="exportPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="{{ route('operational.checklist.export') }}" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title">Print Daily Activity Checklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exportDate" class="form-label text-muted small fw-bold">Checklist Date</label>
                        <input type="date" class="form-control" id="exportDate" name="date"
                            value="{{ old('date', $date) }}" max="{{ $date }}" required>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>The PDF contains the real saved tasks and completion records for the
                        selected date.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-printer me-1"></i> Generate Printout
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($historyRecord)
<!-- Full Details Modal (read-only) -->
<div class="modal fade" id="historyDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ \App\Support\ChecklistHistory::RECORD_TITLE }} &mdash; {{ $historyRecord['date']->format('F j, Y') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Status</div>
                        <div><span class="badge {{ $historyStatusBadges[$historyRecord['status']] ?? 'bg-secondary' }}">{{ \App\Support\ChecklistHistory::statusLabel($historyRecord['status']) }}</span></div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Staff</div>
                        <div>{{ $memberNames($historyRecord['staff']) ?: 'Unknown user' }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Verified By</div>
                        <div>{{ $memberNames($historyRecord['verifiers']) ?: 'Not verified' }}</div>
                        @if ($memberNames($historyRecord['verifiers']))
                            <div class="text-muted small">({{ $memberRoles($historyRecord['verifiers']) }})</div>
                        @endif
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Date</div>
                        <div>{{ $historyRecord['date']->format('F j, Y') }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Time Checked</div>
                        <div>{{ $historyRecord['checked_at']?->format('g:i A') ?? '—' }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Tasks Completed</div>
                        <div>{{ $historyRecord['completed_count'] }} of {{ $historyRecord['total_count'] }}</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 3rem;">#</th>
                                <th>TASK</th>
                                <th>STATUS</th>
                                <th>COMPLETED BY</th>
                                <th>VERIFIED</th>
                                <th>REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historyRecord['tasks'] as $index => $row)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $index + 1 }}</td>
                                    <td>{{ $row['task']->task_name }}</td>
                                    <td><span class="badge {{ $historyStatusBadges[$row['status']] ?? 'bg-secondary' }}">{{ \App\Support\ChecklistHistory::statusLabel($row['status']) }}</span></td>
                                    <td>{{ $row['completed_by'] ?? '—' }}</td>
                                    <td>
                                        {{ $row['verified_by'] ?? '—' }}
                                        @if ($row['verified_at'])
                                            <div class="text-muted small">{{ $row['verified_at']->format('M j, Y g:i A') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $row['remark'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-lock me-1"></i>This is a read-only view of the saved Task_Complete records; historical data cannot be edited from here.
                </p>
            </div>
            <div class="modal-footer">
                <a href="{{ route('operational.checklist.export', ['date' => $historyRecord['date']->toDateString()]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const completeModal = document.getElementById('completeTaskModal');
        if (completeModal) {
            completeModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('completeTaskForm').action = button.getAttribute('data-complete-url');
                document.getElementById('completeTaskName').value = button.getAttribute('data-task-name');
                document.getElementById('completeTaskRemark').value = '';
            });
        }

        const verifyModal = document.getElementById('verifyTaskModal');
        if (verifyModal) {
            verifyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('verifyTaskForm').action = button.getAttribute('data-verify-url');
                document.getElementById('verifyTaskName').value = button.getAttribute('data-task-name');
            });
        }

        const deleteModal = document.getElementById('deleteTaskModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('deleteTaskForm').action = button.getAttribute('data-delete-url');
                document.getElementById('deleteTaskName').textContent = button.getAttribute('data-task-name');
            });
        }

        const editModal = document.getElementById('editTaskModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('editTaskForm').action = button.getAttribute('data-update-url');
                document.getElementById('editTaskName').value = button.getAttribute('data-task-name');
                document.getElementById('editTaskDescription').value = button.getAttribute('data-task-description') || '';
                document.getElementById('editTaskPriority').value = button.getAttribute('data-task-priority');
            });
        }
    });
</script>
@endpush
