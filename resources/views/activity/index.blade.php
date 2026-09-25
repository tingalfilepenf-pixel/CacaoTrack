@extends('layouts.app')

@section('title', 'Activity History')
@section('page_title', 'Activity History')

@section('content')
@php
    $formatDateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A') : '—';
    $initials = fn ($name) => \Illuminate\Support\Str::of($name ?? '?')->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('');
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
        <strong>Please correct the highlighted filters.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Activities</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format($totalActivities) }}</div>
                <div class="text-muted small">Records matching the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Users Involved</div>
                <div class="fs-3 fw-bold">{{ number_format($usersInvolved) }}</div>
                <div class="text-muted small">Distinct users in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Batches Affected</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($batchesAffected) }}</div>
                <div class="text-muted small">Distinct batches in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Activity Types</div>
                <div class="fs-3 fw-bold text-secondary">{{ number_format($activityTypes) }}</div>
                <div class="text-muted small">Distinct activity types in the current filters</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('activity.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Description, user, or batch...">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterUser" class="form-label text-muted small fw-bold mb-1">User</label>
                <select class="form-select form-select-sm" id="filterUser" name="user_id">
                    <option value="">All Users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterType" class="form-label text-muted small fw-bold mb-1">Activity Type</label>
                <select class="form-select form-select-sm" id="filterType" name="activity_type">
                    <option value="">All Activities</option>
                    @foreach (\App\Models\ActivityLog::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['activity_type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected((string) ($filters['batch_id'] ?? '') === (string) $batch->batch_id)>{{ $batch->batch_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold mb-1">Date Range</label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] ?? '' }}" title="From date">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] ?? '' }}" title="To date">
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('activity.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Recent Activities</h6>
        <span class="text-muted small">A traceable record of important operational actions (PRD §24).</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="activityTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">DATE</th>
                        <th>USER</th>
                        <th>ACTIVITY</th>
                        <th>BATCH</th>
                        <th>DESCRIPTION</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php
                            $details = [
                                'type' => $activity->typeLabel(),
                                'user' => $activity->user?->name ?? '—',
                                'batch' => $activity->batch?->batch_code ?? 'N/A',
                                'scion_variety' => $activity->batch?->scionVariety?->scion_variety_name ?? '—',
                                'description' => $activity->description,
                                'activity_date' => $formatDateTime($activity->activity_date),
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 text-nowrap">{{ $formatDateTime($activity->activity_date) }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                        {{ $initials($activity->user?->name) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $activity->user?->name ?? '—' }}</div>
                                        <div class="small text-muted">{{ $activity->user?->email ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge {{ $activity->typeBadgeClass() }}">{{ $activity->typeLabel() }}</span></td>
                            <td>
                                @if ($activity->batch)
                                    <span class="fw-medium">{{ $activity->batch->batch_code }}</span>
                                    <div class="text-muted small">{{ $activity->batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}</div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ \Illuminate\Support\Str::limit($activity->description, 60) }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activityDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No activity records found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $activities->firstItem() ?? 0 }} to {{ $activities->lastItem() ?? 0 }} of {{ $activities->total() }} entries
        </span>
        {{ $activities->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Activity Details Modal -->
<div class="modal fade" id="activityDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Activity Type:</div>
                    <div class="col-7" id="detailsType">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">User:</div>
                    <div class="col-7" id="detailsUser">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Batch:</div>
                    <div class="col-7" id="detailsBatch">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Scion Variety:</div>
                    <div class="col-7" id="detailsVariety">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Date / Time:</div>
                    <div class="col-7" id="detailsDate">&mdash;</div>
                </div>
                <hr>
                <div class="mb-2 text-muted small fw-bold">Description</div>
                <p class="mb-0" id="detailsDescription">&mdash;</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const detailsModal = document.getElementById('activityDetailsModal');
        if (detailsModal) {
            detailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                let details = {};
                try {
                    details = JSON.parse(button.getAttribute('data-details') || '{}');
                } catch (error) {
                    details = {};
                }

                const setText = function (id, value) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value == null || value === '' ? '—' : value;
                    }
                };

                setText('detailsType', details.type);
                setText('detailsUser', details.user);
                setText('detailsBatch', details.batch);
                setText('detailsVariety', details.scion_variety);
                setText('detailsDate', details.activity_date);
                setText('detailsDescription', details.description);
            });
        }
    });
</script>
@endpush
