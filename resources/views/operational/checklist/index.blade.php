@extends('layouts.app')

@section('title', 'Daily Checklist')
@section('page_title', 'Daily Checklist')

@section('page_actions')
<button class="btn btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#exportPdfModal">
    <i class="bi bi-printer me-1"></i> Print Checklist
</button>
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
    <i class="bi bi-plus-lg me-1"></i> Add Task
</button>
@endsection

@section('content')
@php
    $priorityBadges = [
        'high' => ['label' => 'High', 'class' => 'bg-danger'],
        'medium' => ['label' => 'Medium', 'class' => 'bg-warning text-dark'],
        'low' => ['label' => 'Low', 'class' => 'bg-secondary'],
    ];
    $isArchivedView = $status === 'archived';
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

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $isArchivedView ? '' : 'active' }}" href="{{ route('operational.checklist') }}">
            <i class="bi bi-list-check me-1"></i> Active Tasks
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $isArchivedView ? 'active' : '' }}" href="{{ route('operational.checklist', ['status' => 'archived']) }}">
            <i class="bi bi-archive me-1"></i> Archived
            @if ($archivedCount > 0)
                <span class="badge bg-secondary ms-1">{{ $archivedCount }}</span>
            @endif
        </a>
    </li>
</ul>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($isArchivedView)
                <i class="bi bi-archive me-2"></i>Archived Tasks
            @else
                <i class="bi bi-calendar3 me-2"></i>Date: {{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}
            @endif
        </h6>
        @unless ($isArchivedView)
            <div>
                <span class="badge bg-success me-2">{{ $completedCount }} Completed</span>
                <span class="badge bg-warning text-dark">{{ $pendingCount }} Pending</span>
            </div>
        @endunless
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
                                @if ($isArchivedView && $task->archived_at)
                                    <div class="text-muted small fw-normal">
                                        Archived {{ $task->archived_at->format('M j, Y g:i A') }}
                                    </div>
                                @endif
                                @if ($completion)
                                    <div class="text-muted small fw-normal">
                                        Completed {{ $completion->completion_date?->format('M j, Y') }}
                                        by {{ $completion->user?->name ?? 'Unknown user' }}
                                        @if ($completion->remark)
                                            &mdash; &ldquo;{{ $completion->remark }}&rdquo;
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                            <td>
                                @if ($isArchivedView)
                                    <span class="badge bg-secondary">Archived</span>
                                @elseif ($completion)
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if ($isArchivedView)
                                    <form method="POST" action="{{ route('operational.checklist.tasks.restore', $task) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    @unless ($completion)
                                        <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#completeTaskModal"
                                            data-complete-url="{{ route('operational.checklist.tasks.complete', $task) }}"
                                            data-task-name="{{ $task->task_name }}" title="Mark Complete">
                                            <i class="bi bi-check2-square"></i>
                                        </button>
                                    @endunless
                                @endif
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                    data-update-url="{{ route('operational.checklist.tasks.update', $task) }}"
                                    data-task-name="{{ $task->task_name }}"
                                    data-task-description="{{ $task->description }}"
                                    data-task-priority="{{ $task->priority }}" title="View/Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @unless ($isArchivedView)
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveTaskModal"
                                        data-archive-url="{{ route('operational.checklist.tasks.archive', $task) }}"
                                        data-task-name="{{ $task->task_name }}" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endunless
                                @if ($isArchivedView && auth()->user()->canDeleteChecklistTasks())
                                    @if ($task->canBeDeleted())
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTaskModal"
                                            data-delete-url="{{ route('operational.checklist.tasks.destroy', $task) }}"
                                            data-task-name="{{ $task->task_name }}" title="Delete permanently">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                            title="This task has completion history and cannot be deleted. Keep it archived.">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>
                                @if ($isArchivedView)
                                    No archived tasks. Archived tasks appear here and can be restored.
                                @else
                                    No tasks yet. Use &ldquo;Add Task&rdquo; to create the first checklist item.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                        <i class="bi bi-info-circle me-1"></i>Completing a task records your account as the recorder and verifier for today.
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

<!-- Archive Task Modal -->
<div class="modal fade" id="archiveTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="archiveTaskForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Archive Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Archive <strong id="archiveTaskName"></strong>?
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the task from the active checklist but keeps its
                        completion history. You can restore it anytime from the Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if (auth()->user()->canDeleteChecklistTasks())
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
                        <i class="bi bi-exclamation-triangle me-1"></i>This cannot be undone. Only archived tasks with no
                        completion history can be deleted; tasks with history stay archived so their records are preserved.
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

        const archiveModal = document.getElementById('archiveTaskModal');
        if (archiveModal) {
            archiveModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('archiveTaskForm').action = button.getAttribute('data-archive-url');
                document.getElementById('archiveTaskName').textContent = button.getAttribute('data-task-name');
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
