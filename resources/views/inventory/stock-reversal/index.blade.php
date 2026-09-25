@extends('layouts.app')

@section('title', 'Stock Reversal')
@section('page_title', 'Stock Reversal')

@php
    $hasReversible = count($reversibleOptions['in']) + count($reversibleOptions['out']) > 0;
@endphp

@section('page_actions')
<button type="button" class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#reversalModal" @disabled(! $hasReversible)>
    <i class="bi bi-arrow-return-left me-1"></i> Create Reversal
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
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Quantity Reversed</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format($totalReversed) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Reversal Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalReversals) }}</div>
                <div class="text-muted small">Reversal records in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-In Reversals</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format($stockInReversals) }}</div>
                <div class="text-muted small">Reversals of received stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-Out Reversals</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($stockOutReversals) }}</div>
                <div class="text-muted small">Reversals of released stock</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.stock-reversal') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reason or batch code...">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterType" class="form-label text-muted small fw-bold mb-1">Type</label>
                <select class="form-select form-select-sm" id="filterType" name="type">
                    <option value="">All Types</option>
                    <option value="in" @selected(($filters['type'] ?? null) === 'in')>Stock In</option>
                    <option value="out" @selected(($filters['type'] ?? null) === 'out')>Stock Out</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterDateFrom" class="form-label text-muted small fw-bold mb-1">From</label>
                <input type="date" class="form-control form-control-sm" id="filterDateFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="filterDateTo" class="form-label text-muted small fw-bold mb-1">To</label>
                <input type="date" class="form-control form-control-sm" id="filterDateTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.stock-reversal') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Reversal Table -->
<div class="card mb-4 border-warning">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Stock Reversal History</h6>
        <span class="text-muted small">A reversal corrects the inventory balance of an earlier Stock In or Stock Out.</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="stockReversalTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">REVERSAL ID</th>
                        <th>STOCK RECORD</th>
                        <th>TYPE</th>
                        <th>BATCH</th>
                        <th>QUANTITY</th>
                        <th>REASON</th>
                        <th>DATE</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reversals as $reversal)
                        @php
                            $batch = $reversal->sourceBatch();
                            $details = [
                                'reference' => $reversal->reference(),
                                'source_reference' => $reversal->sourceReference(),
                                'type' => $reversal->typeLabel(),
                                'batch_code' => $batch?->batch_code ?? '—',
                                'variety' => $batch?->variety?->variety_name ?? '—',
                                'quantity' => (int) $reversal->quantity,
                                'reason' => $reversal->reason,
                                'reversed_at' => $formatDate($reversal->reversed_at),
                                'recorded_by' => $reversal->user?->name ?? '—',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-warning">{{ $reversal->reference() }}</td>
                            <td>{{ $reversal->sourceReference() }}</td>
                            <td>
                                <span class="badge {{ $reversal->type() === \App\Models\StockReversal::TYPE_IN ? 'bg-primary' : 'bg-danger' }}">
                                    {{ $reversal->typeLabel() }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-medium">{{ $batch?->batch_code ?? '—' }}</span>
                                <div class="text-muted small">{{ $batch?->variety?->variety_name ?? '—' }}</div>
                            </td>
                            <td><span class="badge bg-warning text-dark">{{ number_format((int) $reversal->quantity) }}</span></td>
                            <td class="text-muted small">{{ \Illuminate\Support\Str::limit($reversal->reason, 40) }}</td>
                            <td>{{ $formatDate($reversal->reversed_at) }}</td>
                            <td class="text-muted small">{{ $reversal->user?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reversalDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No stock reversals found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $reversals->firstItem() ?? 0 }} to {{ $reversals->lastItem() ?? 0 }} of {{ $reversals->total() }} entries
        </span>
        {{ $reversals->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- New Reversal Modal -->
<div class="modal fade" id="reversalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Create Stock Reversal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reversalForm" method="POST" action="{{ route('inventory.stock-reversal.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> A reversal is a corrective transaction. It adjusts the inventory to undo a previous Stock In or Stock Out.
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="reversalType">Transaction Type to Reverse <span class="text-danger">*</span></label>
                        <select class="form-select @error('type', 'reversal') is-invalid @enderror" id="reversalType" name="type" required>
                            <option value="" disabled @selected(old('type') === null)>Select Type</option>
                            <option value="in" @selected(old('type') === 'in')>Stock In</option>
                            <option value="out" @selected(old('type') === 'out')>Stock Out</option>
                        </select>
                        @error('type', 'reversal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="reversalSource">Stock Record <span class="text-danger">*</span></label>
                        <select class="form-select @error('source_id', 'reversal') is-invalid @enderror" id="reversalSource" name="source_id" required disabled>
                            <option value="" disabled selected>Select Record</option>
                        </select>
                        <div class="form-text">Only transactions that still have a reversible balance are listed.</div>
                        @error('source_id', 'reversal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="reversalQuantity">Quantity to Reverse <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('quantity', 'reversal') is-invalid @enderror" id="reversalQuantity"
                            name="quantity" min="1" step="1" required placeholder="Enter quantity" value="{{ old('quantity') }}">
                        <div class="form-text" id="reversibleHint"></div>
                        @error('quantity', 'reversal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="reversalReason">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('reason', 'reversal') is-invalid @enderror" id="reversalReason"
                            name="reason" rows="3" maxlength="255" required placeholder="Explain why this transaction is being reversed...">{{ old('reason') }}</textarea>
                        @error('reason', 'reversal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="reversalDate">Reversal Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('reversed_at', 'reversal') is-invalid @enderror" id="reversalDate"
                            name="reversed_at" required max="{{ now()->toDateString() }}" value="{{ old('reversed_at', now()->toDateString()) }}">
                        @error('reversed_at', 'reversal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold" id="reversalSubmitBtn">Save Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reversal Details Modal -->
<div class="modal fade" id="reversalDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Reversal Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Stock Record:</div>
                    <div class="col-7" id="detailsSource">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Type:</div>
                    <div class="col-7" id="detailsType">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Batch:</div>
                    <div class="col-7" id="detailsBatch">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Variety:</div>
                    <div class="col-7" id="detailsVariety">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Quantity Reversed:</div>
                    <div class="col-7 text-warning fw-bold" id="detailsQuantity">&mdash;</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Reason:</div>
                    <div class="col-7" id="detailsReason">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Reversal Date:</div>
                    <div class="col-7" id="detailsDate">&mdash;</div>
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
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reversalModalEl = document.getElementById('reversalModal');
        const sourceOptions = @json($reversibleOptions);
        const typeSelect = document.getElementById('reversalType');
        const sourceSelect = document.getElementById('reversalSource');
        const quantityInput = document.getElementById('reversalQuantity');
        const reversibleHint = document.getElementById('reversibleHint');
        const initialSource = "{{ old('source_id') }}";

        @if ($errors->reversal->any())
            new bootstrap.Modal(reversalModalEl).show();
        @endif

        const populateSources = function (type, selected) {
            sourceSelect.innerHTML = '';
            const options = sourceOptions[type] || [];

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = !selected;
            placeholder.textContent = options.length ? 'Select Record' : 'No reversible transactions';
            sourceSelect.appendChild(placeholder);

            options.forEach(function (option) {
                const opt = document.createElement('option');
                opt.value = option.id;
                opt.textContent = option.label;
                opt.setAttribute('data-max', option.max);
                if (String(option.id) === String(selected)) {
                    opt.selected = true;
                }
                sourceSelect.appendChild(opt);
            });

            sourceSelect.disabled = !type || options.length === 0;
        };

        const refreshMax = function () {
            const option = sourceSelect.options[sourceSelect.selectedIndex];

            if (sourceSelect.value && option && option.getAttribute('data-max')) {
                const max = parseInt(option.getAttribute('data-max'), 10);
                quantityInput.setAttribute('max', max);
                reversibleHint.textContent = 'Reversible now: ' + max.toLocaleString() + ' unit(s).';
            } else {
                quantityInput.removeAttribute('max');
                reversibleHint.textContent = '';
            }
        };

        if (typeSelect && sourceSelect) {
            typeSelect.addEventListener('change', function () {
                populateSources(typeSelect.value, null);
                refreshMax();
            });

            sourceSelect.addEventListener('change', refreshMax);

            if (typeSelect.value) {
                populateSources(typeSelect.value, initialSource);
                refreshMax();
            }
        }

        const detailsModal = document.getElementById('reversalDetailsModal');
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

                setText('detailsReference', details.reference);
                setText('detailsSource', details.source_reference);
                setText('detailsType', details.type);
                setText('detailsBatch', details.batch_code);
                setText('detailsVariety', details.variety);
                setText('detailsQuantity', Number(details.quantity || 0).toLocaleString());
                setText('detailsReason', details.reason);
                setText('detailsDate', details.reversed_at);
                setText('detailsRecordedBy', details.recorded_by);
            });
        }

        const form = document.getElementById('reversalForm');
        const submitButton = document.getElementById('reversalSubmitBtn');
        if (form && submitButton) {
            form.addEventListener('submit', function () {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }
    });
</script>
@endpush
