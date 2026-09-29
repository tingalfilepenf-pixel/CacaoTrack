@extends('layouts.app')

@section('title', 'Regraft')
@section('page_title', 'Regraft')

@section('page_actions')
<button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#recordRegraftModal">
    <i class="bi bi-plus-lg me-1"></i> Record Regraft
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
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Regraft Deficit</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format($totalDeficit) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Trees that still need to be regrafted for the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Regrafted</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalRegrafted) }}</div>
                <div class="text-muted small">Quantity regrafted across the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Mortality</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($totalMortality) }}</div>
                <div class="text-muted small">Effective mortality for the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Batches With Deficit</div>
                <div class="fs-3 fw-bold">{{ number_format($batchesWithDeficit) }}</div>
                <div class="text-muted small">Batches still awaiting regraft</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('operational.regraft') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($summaries as $summary)
                        <option value="{{ $summary['batch']->batch_id }}" @selected(($filters['batch_id'] ?? null) == $summary['batch']->batch_id)>
                            {{ $summary['batch']->batch_code }} ({{ $summary['batch']->scionVariety?->scion_variety_name ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                <a href="{{ route('operational.regraft') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Regraft Status by Batch -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Regraft Status by Batch</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH</th>
                        <th>SCION VARIETY</th>
                        <th class="text-end">TOTAL GRAFTED</th>
                        <th class="text-end">REGRAFT</th>
                        <th class="text-end">MORTALITY</th>
                        <th class="text-end">DEFICIT</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        @php
                            $batch = $summary['batch'];
                            $deficitClass = $summary['deficit'] === 0 ? 'bg-success' : ($summary['deficit'] <= 20 ? 'bg-warning text-dark' : 'bg-danger');
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium">
                                {{ $batch->batch_code }}
                                @if ($batch->isSubBatch())
                                    <span class="badge bg-info text-dark ms-1">Sub-batch</span>
                                @endif
                            </td>
                            <td>{{ $batch->scionVariety?->scion_variety_name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($summary['total_grafted']) }}</td>
                            <td class="text-end"><span class="text-success fw-bold">{{ number_format($summary['regraft']) }}</span></td>
                            <td class="text-end">{{ number_format($summary['effective_mortality']) }}</td>
                            <td class="text-end">
                                <span class="badge {{ $deficitClass }} fs-6">{{ number_format($summary['deficit']) }}</span>
                            </td>
                            <td class="text-end pe-4">
                                @if ($summary['regraftable'] > 0 && ! $batch->isArchived())
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#recordRegraftModal"
                                        data-batch-id="{{ $batch->batch_id }}" title="Record a regraft from this batch">
                                        <i class="bi bi-arrow-repeat"></i> Regraft
                                    </button>
                                @elseif ($batch->isArchived())
                                    <span class="text-muted small">Archived</span>
                                @else
                                    <span class="text-muted small">Nothing to regraft</span>
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

<!-- Regraft History -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Regraft History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">DATE</th>
                        <th>SOURCE BATCH</th>
                        <th>NEW SUB-BATCH</th>
                        <th>SUB-BATCH VARIETY</th>
                        <th class="text-end">QUANTITY</th>
                        <th>REMARK</th>
                        <th class="pe-4">RECORDED BY</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $record)
                        @php
                            $source = $record->parent ?? $record->batch;
                        @endphp
                        <tr>
                            <td class="ps-4">{{ $formatDate($record->regraft_date) }}</td>
                            <td class="fw-medium">{{ $source?->batch_code ?? '—' }}</td>
                            <td><span class="badge bg-info text-dark">{{ $record->batch?->batch_code ?? '—' }}</span></td>
                            <td>{{ $record->batch?->scionVariety?->scion_variety_name ?? '—' }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($record->quantity) }}</td>
                            <td class="text-muted small">{{ $record->remark ?? '—' }}</td>
                            <td class="pe-4">{{ $record->user?->name ?? 'Unknown user' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No regraft records found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($history->hasPages())
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
            <span class="text-muted small">
                Showing {{ $history->firstItem() ?? 0 }} to {{ $history->lastItem() ?? 0 }} of {{ $history->total() }} entries
            </span>
            {{ $history->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

<!-- Record Regraft Modal -->
<div class="modal fade" id="recordRegraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Record Regraft</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('operational.regraft.records.store') }}" id="recordRegraftForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Regrafted plants move to a new sub-batch (e.g. B001-R1) with its own 21-day cycle.
                    </div>

                    <div class="mb-3">
                        <label for="recordBatch" class="form-label text-muted small fw-bold">Source Batch <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'regraft') is-invalid @enderror" id="recordBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($regraftableBatches as $summary)
                                @php $batch = $summary['batch']; @endphp
                                <option value="{{ $batch->batch_id }}"
                                    data-code="{{ $batch->batch_code }}"
                                    data-variety-id="{{ $batch->scion_variety_id }}"
                                    data-regraftable="{{ $summary['regraftable'] }}"
                                    data-grafted-date="{{ $batch->grafted_date?->format('Y-m-d') }}"
                                    @selected(old('batch_id') == $batch->batch_id)>
                                    {{ $batch->batch_code }} ({{ $batch->scionVariety?->scion_variety_name ?? 'No scion' }}) — {{ $summary['regraftable'] }} regraftable
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'regraft')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if ($regraftableBatches->isEmpty())
                            <div class="form-text text-warning">No batches currently have a quantity available for regrafting.</div>
                        @endif
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recordQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'regraft') is-invalid @enderror" id="recordQuantity"
                                name="quantity" min="1" value="{{ old('quantity') }}" required>
                            <div class="form-text" id="recordQuantityHint"></div>
                            @error('quantity', 'regraft')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="recordDate" class="form-label text-muted small fw-bold">Regraft Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('regraft_date', 'regraft') is-invalid @enderror" id="recordDate"
                                name="regraft_date" max="{{ now()->toDateString() }}" value="{{ old('regraft_date', now()->toDateString()) }}" required>
                            @error('regraft_date', 'regraft')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="recordScionVariety" class="form-label text-muted small fw-bold">New Sub-Batch Scion Variety <span class="text-danger">*</span></label>
                        <select class="form-select @error('scion_variety_id', 'regraft') is-invalid @enderror" id="recordScionVariety" name="scion_variety_id" required>
                            <option value="" disabled @selected(old('scion_variety_id') === null)>Select Scion Variety</option>
                            @foreach ($scionVarieties as $scionVariety)
                                <option value="{{ $scionVariety->scion_variety_id }}" @selected(old('scion_variety_id') == $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Defaults to the source batch's variety; the sub-batch may use a different one.</div>
                        @error('scion_variety_id', 'regraft')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="recordRemark" class="form-label text-muted small fw-bold">Remarks</label>
                        <textarea class="form-control" id="recordRemark" name="remark" rows="2" maxlength="255" placeholder="Optional details...">{{ old('remark') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold" id="recordRegraftSubmitBtn">Save Regraft</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('recordRegraftModal');
        const batchSelect = document.getElementById('recordBatch');
        const quantityInput = document.getElementById('recordQuantity');
        const varietySelect = document.getElementById('recordScionVariety');
        const dateInput = document.getElementById('recordDate');
        const hint = document.getElementById('recordQuantityHint');

        const applyBatchConstraints = function () {
            const option = batchSelect.options[batchSelect.selectedIndex];
            const regraftable = option ? parseInt(option.getAttribute('data-regraftable') || '0', 10) : 0;
            const varietyId = option ? option.getAttribute('data-variety-id') : null;
            const graftedDate = option ? option.getAttribute('data-grafted-date') : null;

            if (regraftable > 0) {
                quantityInput.max = regraftable;
                hint.textContent = 'Up to ' + regraftable + ' available for regrafting.';
            } else {
                quantityInput.removeAttribute('max');
                hint.textContent = '';
            }

            if (graftedDate) {
                dateInput.min = graftedDate;
            } else {
                dateInput.removeAttribute('min');
            }

            if (varietyId) {
                varietySelect.value = varietyId;
            }
        };

        if (batchSelect) {
            batchSelect.addEventListener('change', applyBatchConstraints);
        }

        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (button && button.getAttribute('data-batch-id')) {
                    batchSelect.value = button.getAttribute('data-batch-id');
                }

                applyBatchConstraints();
                quantityInput.focus();
            });
        }

        const form = document.getElementById('recordRegraftForm');
        if (form) {
            form.addEventListener('submit', function () {
                const btn = document.getElementById('recordRegraftSubmitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }
    });
</script>
@endpush
