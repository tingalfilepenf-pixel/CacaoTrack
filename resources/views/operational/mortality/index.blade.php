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
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->getBags() as $bag)
                @foreach ($bag->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
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
                            {{ $batch->batch_code }} ({{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterScionVariety" class="form-label text-muted small fw-bold mb-1">Scion Variety</label>
                <select class="form-select form-select-sm" id="filterScionVariety" name="scion_variety_id">
                    <option value="">All Scion Varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(($filters['scion_variety_id'] ?? null) == $scionVariety->scion_variety_id)>
                            {{ $scionVariety->scion_variety_name }}
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
                        <th>SCION VARIETY</th>
                        <th>DATE</th>
                        <th class="text-end">QUANTITY</th>
                        <th>CAUSE</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $details = [
                                'batch_code' => $record->batch?->batch_code ?? '—',
                                'scion_variety' => $record->batch?->scionVariety?->scion_variety_name ?? 'Not assigned',
                                'date' => $formatDate($record->recorded_at),
                                'cause' => $record->cause,
                                'quantity' => (int) $record->quantity,
                                'recorded_by' => $record->user?->name ?? 'Unknown user',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium">{{ $record->batch?->batch_code ?? '—' }}</td>
                            <td>{{ $record->batch?->scionVariety?->scion_variety_name ?? 'Not assigned' }}</td>
                            <td>{{ $formatDate($record->recorded_at) }}</td>
                            <td class="text-end"><span class="badge bg-danger">{{ number_format((int) $record->quantity) }}</span></td>
                            <td>{{ $record->cause }}</td>
                            <td class="text-muted small">{{ $record->user?->name ?? 'Unknown user' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailsModal"
                                    data-details="{{ json_encode($details) }}" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                @if (auth()->user()->hasPermission('correct_records'))
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#correctMortalityModal"
                                        data-action="{{ route('corrections.mortality', $record) }}"
                                        data-batch="{{ $record->batch?->batch_code ?? '—' }}"
                                        data-quantity="{{ (int) $record->quantity }}"
                                        data-recorded-at="{{ $record->recorded_at?->toDateString() }}"
                                        data-cause="{{ $record->cause }}"
                                        title="Correct this entry">
                                        <i class="bi bi-pencil"></i> Correct
                                    </button>
                                @endif
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
            <form id="recordMortalityForm" method="POST" action="{{ route('operational.mortality.records.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recordBatch" class="form-label text-muted small fw-bold">Batch <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'mortality') is-invalid @enderror" id="recordBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($activeBatches as $batch)
                                @php $breakdown = $activeBatchBreakdowns[$batch->batch_id] ?? null; @endphp
                                <option value="{{ $batch->batch_id }}" @selected(old('batch_id') == $batch->batch_id)>
                                    {{ $batch->batch_code }} ({{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }})@if ($breakdown) — Original: {{ number_format($breakdown['original_quantity']) }} | Stock Out: {{ number_format($breakdown['stock_out_quantity']) }} | Remaining: {{ number_format($breakdown['remaining_quantity']) }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recordQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'mortality') is-invalid @enderror" id="recordQuantity"
                                name="quantity" min="1" value="{{ old('quantity') }}" required>
                            @error('quantity', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="recordDate" class="form-label text-muted small fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('recorded_at', 'mortality') is-invalid @enderror" id="recordDate"
                                name="recorded_at" max="{{ now()->toDateString() }}" value="{{ old('recorded_at', now()->toDateString()) }}" required>
                            @error('recorded_at', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="recordCause" class="form-label text-muted small fw-bold">Cause of Death <span class="text-danger">*</span></label>
                        <select class="form-select @error('cause', 'mortality') is-invalid @enderror" id="recordCause" name="cause" required>
                            <option value="" disabled @selected(old('cause') === null)>Select cause of death</option>
                            @foreach (\App\Models\MortalityRecord::CAUSES as $causeOption)
                                <option value="{{ $causeOption }}" @selected(old('cause') === $causeOption)>{{ $causeOption }}</option>
                            @endforeach
                        </select>
                        @error('cause', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 d-none" id="recordCauseOtherWrapper">
                        <label for="recordCauseOther" class="form-label text-muted small fw-bold">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('cause_other', 'mortality') is-invalid @enderror" id="recordCauseOther"
                            name="cause_other" maxlength="200" value="{{ old('cause_other') }}" placeholder="Specify the cause of death">
                        @error('cause_other', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>An Administrator can correct a mistaken entry with the
                        <strong>Correct</strong> action; a dead root stock cannot be regrafted — it is counted as mortality.
                        Mortality cannot exceed the batch's remaining quantity (original less Stock Out and prior mortality), including when corrected.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="recordMortalitySubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mortality Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Batch:</div>
                    <div class="col-7" id="detailsBatch">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Scion Variety:</div>
                    <div class="col-7" id="detailsScionVariety">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Date:</div>
                    <div class="col-7" id="detailsDate">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Cause:</div>
                    <div class="col-7" id="detailsCause">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Quantity:</div>
                    <div class="col-7 text-danger fw-bold" id="detailsQuantity">&mdash;</div>
                </div>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Recorded By:</div>
                    <div class="col-7" id="detailsRecordedBy">&mdash;</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if (auth()->user()->hasPermission('correct_records'))
<!-- Correct Mortality Modal (administrator only — OD-5) -->
<div class="modal fade" id="correctMortalityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>Correct Mortality Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="correctMortalityForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        Editing the entry re-validates it against the batch's remaining quantity and records the
                        before/after values in Activity History.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Batch</label>
                        <input type="text" class="form-control" id="correctBatchLabel" value="" readonly>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="correctQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'mortality') is-invalid @enderror" id="correctQuantity"
                                name="quantity" min="1" required>
                            @error('quantity', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="correctDate" class="form-label text-muted small fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('recorded_at', 'mortality') is-invalid @enderror" id="correctDate"
                                name="recorded_at" max="{{ now()->toDateString() }}" required>
                            @error('recorded_at', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="correctCause" class="form-label text-muted small fw-bold">Cause of Death <span class="text-danger">*</span></label>
                        <select class="form-select @error('cause', 'mortality') is-invalid @enderror" id="correctCause" name="cause" required>
                            @foreach (\App\Models\MortalityRecord::CAUSES as $causeOption)
                                <option value="{{ $causeOption }}">{{ $causeOption }}</option>
                            @endforeach
                        </select>
                        @error('cause', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 d-none" id="correctCauseOtherWrapper">
                        <label for="correctCauseOther" class="form-label text-muted small fw-bold">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('cause_other', 'mortality') is-invalid @enderror" id="correctCauseOther"
                            name="cause_other" maxlength="200" placeholder="Specify the cause of death">
                        @error('cause_other', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Correction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const recordModalEl = document.getElementById('recordMortalityModal');

        @if ($errors->mortality->any())
            new bootstrap.Modal(recordModalEl).show();
        @endif

        @if ($errors->mortality_correction->any())
            const correctMortalityErrorEl = document.getElementById('correctMortalityModal');
            if (correctMortalityErrorEl) {
                new bootstrap.Modal(correctMortalityErrorEl).show();
            }
        @endif

        const detailsModal = document.getElementById('detailsModal');
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

                setText('detailsBatch', details.batch_code);
                setText('detailsScionVariety', details.scion_variety);
                setText('detailsDate', details.date);
                setText('detailsCause', details.cause);
                setText('detailsQuantity', Number(details.quantity || 0).toLocaleString());
                setText('detailsRecordedBy', details.recorded_by);
            });
        }

        const correctMortalityModal = document.getElementById('correctMortalityModal');
        if (correctMortalityModal) {
            correctMortalityModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                document.getElementById('correctMortalityForm').action = button.getAttribute('data-action');
                document.getElementById('correctBatchLabel').value = button.getAttribute('data-batch') || '';
                document.getElementById('correctQuantity').value = button.getAttribute('data-quantity') || '';
                document.getElementById('correctDate').value = button.getAttribute('data-recorded-at') || '';

                const others = @json(\App\Models\MortalityRecord::CAUSE_OTHERS);
                const cause = button.getAttribute('data-cause') || '';
                const select = document.getElementById('correctCause');
                const otherWrapper = document.getElementById('correctCauseOtherWrapper');
                const otherInput = document.getElementById('correctCauseOther');

                if (cause === others || cause.indexOf(others + ':') === 0) {
                    select.value = others;
                    otherInput.value = cause === others ? '' : cause.substring(others.length + 2);
                } else {
                    select.value = cause;
                    otherInput.value = '';
                }

                otherWrapper.classList.toggle('d-none', select.value !== others);
                otherInput.required = select.value === others;
            });
        }

        const othersCause = @json(\App\Models\MortalityRecord::CAUSE_OTHERS);
        const causeSelect = document.getElementById('recordCause');
        const causeOtherWrapper = document.getElementById('recordCauseOtherWrapper');
        const causeOtherInput = document.getElementById('recordCauseOther');

        const syncCauseOther = function () {
            if (!causeSelect || !causeOtherWrapper) {
                return;
            }

            const isOthers = causeSelect.value === othersCause;
            causeOtherWrapper.classList.toggle('d-none', !isOthers);

            if (causeOtherInput) {
                causeOtherInput.required = isOthers;
            }
        };

        if (causeSelect) {
            causeSelect.addEventListener('change', syncCauseOther);
            syncCauseOther();
        }

        const correctCauseSelect = document.getElementById('correctCause');
        if (correctCauseSelect) {
            correctCauseSelect.addEventListener('change', function () {
                const isOthers = correctCauseSelect.value === othersCause;
                document.getElementById('correctCauseOtherWrapper').classList.toggle('d-none', !isOthers);
                document.getElementById('correctCauseOther').required = isOthers;
            });
        }

        const form = document.getElementById('recordMortalityForm');
        if (form) {
            form.addEventListener('submit', function () {
                syncCauseOther();

                const btn = document.getElementById('recordMortalitySubmitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }
    });
</script>
@endpush
