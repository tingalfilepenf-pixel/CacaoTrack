@extends('layouts.app')

@section('title', 'Mortality Tracking')
@section('page_title', 'Mortality Tracking')

@section('page_actions')
<button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#recordMortalityModal">
    <i class="bi bi-plus-lg me-1"></i> Record Mortality
</button>
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
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
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Mortality</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($totalMortality) }}</div>
                <div class="text-muted small">Effective quantity for the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Mortality Records</div>
                <div class="fs-3 fw-bold">{{ number_format($recordCount) }}</div>
                <div class="text-muted small">Number of recorded entries</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Batches Affected</div>
                <div class="fs-3 fw-bold">{{ number_format($batchesAffected) }}</div>
                <div class="text-muted small">Distinct batches in the current filters</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('operational.mortality') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>
                            {{ $batch->batch_code }} ({{ $batch->variety?->variety_name ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterVariety" class="form-label text-muted small fw-bold mb-1">Variety</label>
                <select class="form-select form-select-sm" id="filterVariety" name="variety_id">
                    <option value="">All Varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected(($filters['variety_id'] ?? null) == $variety->variety_id)>
                            {{ $variety->variety_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterDateFrom" class="form-label text-muted small fw-bold mb-1">Date From</label>
                <input type="date" class="form-control form-control-sm" id="filterDateFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="filterDateTo" class="form-label text-muted small fw-bold mb-1">Date To</label>
                <input type="date" class="form-control form-control-sm" id="filterDateTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill">Filter</button>
                <a href="{{ route('operational.mortality') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mortality Records Table -->
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH</th>
                        <th>VARIETY</th>
                        <th>DATE</th>
                        <th>QUANTITY</th>
                        <th>CAUSE</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $effective = $record->effectiveQuantity();
                            $adjustmentTotal = $effective - (int) $record->quantity;
                            $adjustments = $record->adjustments;
                            $adjustmentData = $adjustments->map(fn ($adjustment) => [
                                'date' => $adjustment->adjusted_at?->format('M j, Y g:i A'),
                                'quantity' => $adjustment->adjustment_quantity,
                                'reason' => $adjustment->reason,
                                'approver' => $adjustment->approver?->name ?? 'Unknown user',
                            ])->values();
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium">{{ $record->batch?->batch_code ?? '—' }}</td>
                            <td>{{ $record->batch?->variety?->variety_name ?? '—' }}</td>
                            <td>{{ $formatDate($record->recorded_at) }}</td>
                            <td>
                                <span class="badge bg-danger">{{ $effective }}</span>
                                @if ($adjustmentTotal !== 0)
                                    <div class="text-muted small">
                                        base {{ $record->quantity }}
                                        <span class="{{ $adjustmentTotal < 0 ? 'text-success' : 'text-danger' }}">
                                            ({{ $adjustmentTotal > 0 ? '+' : '' }}{{ $adjustmentTotal }})
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $record->cause }}</td>
                            <td class="text-muted small">{{ $record->user?->name ?? 'Unknown user' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#adjustmentModal"
                                    data-adjust-url="{{ route('operational.mortality.records.adjust', $record) }}"
                                    data-batch-code="{{ $record->batch?->batch_code ?? '—' }}"
                                    data-cause="{{ $record->cause }}"
                                    data-base-quantity="{{ $record->quantity }}"
                                    data-effective-quantity="{{ $effective }}"
                                    title="Adjust Record">
                                    <i class="bi bi-sliders"></i> Adjust
                                </button>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailsModal"
                                    data-record-id="{{ $record->mortality_id }}"
                                    data-batch-code="{{ $record->batch?->batch_code ?? '—' }}"
                                    data-variety="{{ $record->batch?->variety?->variety_name ?? '—' }}"
                                    data-date="{{ $formatDate($record->recorded_at) }}"
                                    data-cause="{{ $record->cause }}"
                                    data-base-quantity="{{ $record->quantity }}"
                                    data-effective-quantity="{{ $effective }}"
                                    data-recorded-by="{{ $record->user?->name ?? 'Unknown user' }}"
                                    data-adjustments="{{ $adjustmentData->toJson() }}"
                                    title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-x fs-3 d-block mb-2"></i>
                                No mortality records found. Use &ldquo;Record Mortality&rdquo; to add the first entry.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries
        </span>
        {{ $records->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Record Mortality Modal -->
<div class="modal fade" id="recordMortalityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Record Mortality</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('operational.mortality.records.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recordBatch" class="form-label text-muted small fw-bold">Batch</label>
                        <select class="form-select" id="recordBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($activeBatches as $batch)
                                <option value="{{ $batch->batch_id }}" @selected(old('batch_id') == $batch->batch_id)>
                                    {{ $batch->batch_code }} ({{ $batch->variety?->variety_name ?? '—' }}) — remaining: {{ $batch->remainingMortalityCapacity() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recordQuantity" class="form-label text-muted small fw-bold">Quantity</label>
                            <input type="number" class="form-control" id="recordQuantity" name="quantity" min="1" value="{{ old('quantity') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="recordDate" class="form-label text-muted small fw-bold">Date</label>
                            <input type="date" class="form-control" id="recordDate" name="recorded_at" value="{{ old('recorded_at', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="recordCause" class="form-label text-muted small fw-bold">Cause</label>
                        <input type="text" class="form-control" id="recordCause" name="cause" list="causeOptions" maxlength="255" value="{{ old('cause') }}" placeholder="Select or type a cause" required>
                        <datalist id="causeOptions">
                            <option value="Disease"></option>
                            <option value="Pest"></option>
                            <option value="Drought"></option>
                            <option value="Physical Damage"></option>
                            <option value="Other"></option>
                        </datalist>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Mortality cannot exceed the batch's remaining viable quantity.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mortality Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Mortality Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="adjustMortalityForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Adjustments should only be made for correction purposes.
                    </div>

                    <div class="mb-2 row align-items-center">
                        <div class="col-sm-5">
                            <label class="form-label text-muted small fw-bold mb-0">Batch / Cause:</label>
                        </div>
                        <div class="col-sm-7 fw-bold" id="adjustBatchCause">&mdash;</div>
                    </div>

                    <div class="mb-2 row align-items-center">
                        <div class="col-sm-5">
                            <label class="form-label text-muted small fw-bold mb-0">Original Quantity:</label>
                        </div>
                        <div class="col-sm-7 fw-bold" id="adjustBaseQuantity">&mdash;</div>
                    </div>

                    <div class="mb-3 row align-items-center">
                        <div class="col-sm-5">
                            <label class="form-label text-muted small fw-bold mb-0">Current Effective:</label>
                        </div>
                        <div class="col-sm-7 fw-bold" id="adjustEffectiveQuantity">&mdash;</div>
                    </div>

                    <div class="mb-3">
                        <label for="adjustQuantity" class="form-label text-muted small fw-bold">Adjustment Quantity</label>
                        <input type="number" class="form-control" id="adjustQuantity" name="adjustment_quantity" required placeholder="e.g. -5 to reduce">
                        <div class="form-text">Signed correction: negative to reduce, positive to increase.</div>
                    </div>

                    <div class="mb-3">
                        <label for="adjustReason" class="form-label text-muted small fw-bold">Reason for Adjustment</label>
                        <textarea class="form-control" id="adjustReason" name="reason" rows="2" maxlength="255" required placeholder="Provide a valid reason..."></textarea>
                    </div>

                    <div class="mb-3 row align-items-center">
                        <div class="col-sm-5">
                            <label class="form-label text-muted small fw-bold mb-0">Approved By:</label>
                        </div>
                        <div class="col-sm-7 text-dark">{{ Auth::user()?->name ?? 'Authenticated user' }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mortality Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Batch</div>
                        <div id="detailsBatch">&mdash;</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Variety</div>
                        <div id="detailsVariety">&mdash;</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Date</div>
                        <div id="detailsDate">&mdash;</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Cause</div>
                        <div id="detailsCause">&mdash;</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Recorded By</div>
                        <div id="detailsRecordedBy">&mdash;</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase">Effective Quantity</div>
                        <div id="detailsEffective">&mdash;</div>
                    </div>
                </div>

                <h6 class="text-muted small fw-bold text-uppercase">Adjustment History</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Adjustment</th>
                                <th>Reason</th>
                                <th>Approved By</th>
                            </tr>
                        </thead>
                        <tbody id="detailsAdjustments">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No adjustments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
        const adjustmentModal = document.getElementById('adjustmentModal');
        if (adjustmentModal) {
            adjustmentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                document.getElementById('adjustMortalityForm').action = button.getAttribute('data-adjust-url');
                document.getElementById('adjustBatchCause').textContent =
                    button.getAttribute('data-batch-code') + ' / ' + button.getAttribute('data-cause');
                document.getElementById('adjustBaseQuantity').textContent = button.getAttribute('data-base-quantity');
                document.getElementById('adjustEffectiveQuantity').textContent = button.getAttribute('data-effective-quantity');
                document.getElementById('adjustQuantity').value = '';
                document.getElementById('adjustReason').value = '';
            });
        }

        const detailsModal = document.getElementById('detailsModal');
        if (detailsModal) {
            detailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                document.getElementById('detailsBatch').textContent = button.getAttribute('data-batch-code');
                document.getElementById('detailsVariety').textContent = button.getAttribute('data-variety');
                document.getElementById('detailsDate').textContent = button.getAttribute('data-date');
                document.getElementById('detailsCause').textContent = button.getAttribute('data-cause');
                document.getElementById('detailsRecordedBy').textContent = button.getAttribute('data-recorded-by');
                document.getElementById('detailsEffective').textContent =
                    button.getAttribute('data-effective-quantity') + ' (base ' + button.getAttribute('data-base-quantity') + ')';

                const tbody = document.getElementById('detailsAdjustments');
                let adjustments = [];
                try {
                    adjustments = JSON.parse(button.getAttribute('data-adjustments') || '[]');
                } catch (error) {
                    adjustments = [];
                }

                if (!adjustments.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No adjustments recorded.</td></tr>';
                    return;
                }

                tbody.innerHTML = adjustments.map(function (adjustment) {
                    const quantity = Number(adjustment.quantity);
                    const cls = quantity < 0 ? 'text-success' : 'text-danger';
                    const label = (quantity > 0 ? '+' : '') + quantity;
                    const escape = function (value) {
                        const div = document.createElement('div');
                        div.textContent = value == null ? '' : value;
                        return div.innerHTML;
                    };

                    return '<tr>' +
                        '<td>' + escape(adjustment.date) + '</td>' +
                        '<td class="fw-bold ' + cls + '">' + escape(label) + '</td>' +
                        '<td>' + escape(adjustment.reason) + '</td>' +
                        '<td>' + escape(adjustment.approver) + '</td>' +
                        '</tr>';
                }).join('');
            });
        }
    });
</script>
@endpush
