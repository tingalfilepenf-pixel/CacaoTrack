@extends('layouts.app')

@section('title', 'Batch Monitoring')
@section('page_title', 'Batch Monitoring')

@section('page_actions')
<button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#startMonitoringModal">
    <i class="bi bi-play-circle me-1"></i> Start Monitoring
</button>
@endsection

@section('content')
@php
    $lifecycleDays = \App\Models\BatchMonitoring::LIFECYCLE_DAYS;
    $unstartedBatches = $allRows->filter(fn ($row) => $row['batch']->monitoring_start_date === null)->values();
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

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Batches</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format($totalBatches) }}</div>
                <div class="text-muted small">Active batches under monitoring</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Pending</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format($pendingCount) }}</div>
                <div class="text-muted small">Still within the 21-day lifecycle</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Complete</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($completeCount) }}</div>
                <div class="text-muted small">Reached the full 21-day lifecycle</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Alive Plants</div>
                <div class="fs-3 fw-bold text-info">{{ number_format($totalAlive) }}</div>
                <div class="text-muted small">Across the listed batches</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('operational.batch-monitoring') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Batch Code</label>
                <input type="text" class="form-control form-control-sm" id="filterSearch" name="search"
                    value="{{ $filters['search'] ?? '' }}" placeholder="Search batch code...">
            </div>
            <div class="col-md-3">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($recordableBatches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>
                            {{ $batch->batch_code }} ({{ $batch->variety?->variety_name ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\BatchMonitoring::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterAsOf" class="form-label text-muted small fw-bold mb-1">Reference Date</label>
                <input type="date" class="form-control form-control-sm" id="filterAsOf" name="as_of"
                    value="{{ $filters['as_of'] ?? $reference->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                <a href="{{ route('operational.batch-monitoring') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Monitoring table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Batch Monitoring Status</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH</th>
                        <th>VARIETY</th>
                        <th>START DATE</th>
                        <th>AGE</th>
                        <th>ALIVE PLANTS</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $batch = $row['batch'];
                            $status = $row['status'];
                            $isComplete = $row['day'] !== null && $row['day'] > $lifecycleDays;
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="fw-medium">{{ $batch->batch_code }}</div>
                            </td>
                            <td>{{ $batch->variety?->variety_name ?? '—' }}</td>
                            <td>{{ $batch->monitoring_start_date?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if ($row['day'] === null)
                                    <span class="text-muted">—</span>
                                @else
                                    <div>Day {{ $row['day'] }} of {{ $lifecycleDays }}</div>
                                    <div class="text-muted small">
                                        {{ $isComplete ? 'Lifecycle complete' : $row['remaining'].' day'.($row['remaining'] === 1 ? '' : 's').' left' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="fw-medium">{{ number_format($row['alive']) }}</span>
                            </td>
                            <td>
                                <span class="badge {{ \App\Models\BatchMonitoring::statusBadgeClass($status) }}">
                                    {{ \App\Models\BatchMonitoring::statusLabel($status) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if ($batch->monitoring_start_date === null)
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#startMonitoringModal"
                                        data-batch-id="{{ $batch->batch_id }}"
                                        data-batch-code="{{ $batch->batch_code }}"
                                        title="Start Monitoring">
                                        <i class="bi bi-play-circle"></i> Start
                                    </button>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No batches found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Start Monitoring Modal -->
<div class="modal fade" id="startMonitoringModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Monitoring</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="startMonitoringForm" action="{{ route('operational.batch-monitoring.batches.start', ['batch' => old('start_batch_id', $unstartedBatches->first()['batch']->batch_id ?? 0)]) }}">
                @csrf
                <div class="modal-body">
                    @if ($unstartedBatches->isEmpty())
                        <p class="text-muted mb-0">Every active batch already has a monitoring start date.</p>
                    @else
                        <div class="mb-3">
                            <label for="startBatch" class="form-label text-muted small fw-bold">Batch</label>
                            <select class="form-select" id="startBatch" name="start_batch_id" required>
                                @foreach ($unstartedBatches as $row)
                                    <option value="{{ $row['batch']->batch_id }}" @selected(old('start_batch_id') == $row['batch']->batch_id)>
                                        {{ $row['batch']->batch_code }} ({{ $row['batch']->variety?->variety_name ?? '—' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label for="startDate" class="form-label text-muted small fw-bold">Monitoring Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="monitoring_start_date"
                                value="{{ old('monitoring_start_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            <div class="form-text">The 21-day lifecycle is counted from this date.</div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold" @disabled($unstartedBatches->isEmpty())>Start Monitoring</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const startModal = document.getElementById('startMonitoringModal');
        if (startModal) {
            startModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                const batchId = button.getAttribute('data-batch-id');
                const select = document.getElementById('startBatch');
                if (batchId && select) {
                    select.value = batchId;
                    select.dispatchEvent(new Event('change'));
                }
            });
        }

        const startForm = document.getElementById('startMonitoringForm');
        const startSelect = document.getElementById('startBatch');
        if (startForm && startSelect) {
            const syncAction = function () {
                const base = @json(route('operational.batch-monitoring.batches.start', ['batch' => '__BATCH__']));
                startForm.setAttribute('action', base.replace('__BATCH__', startSelect.value));
            };
            startSelect.addEventListener('change', syncAction);
            syncAction();
        }
    });
</script>
@endpush
