@extends('layouts.app')

@section('title', 'Stock Out')
@section('page_title', 'Stock Out')

@section('page_actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#clientModal">
        <i class="bi bi-person-plus me-1"></i> New Client
    </button>
    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#stockOutModal">
        <i class="bi bi-box-arrow-right me-1"></i> New Stock Out
    </button>
</div>
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
    $formatAmount = fn ($value) => $value === null || $value === '' ? '—' : '₱'.number_format((float) $value, 2);
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
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Quantity Released</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($totalReleased) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-Out Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalTransactions) }}</div>
                <div class="text-muted small">Release records in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Clients Involved</div>
                <div class="fs-3 fw-bold">{{ number_format($totalClients) }}</div>
                <div class="text-muted small">Distinct clients in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Amount Released</div>
                <div class="fs-3 fw-bold">₱{{ number_format($totalAmount, 2) }}</div>
                <div class="text-muted small">Sum of recorded amounts (where provided)</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.stock-out') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Client or batch code...">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterClient" class="form-label text-muted small fw-bold mb-1">Client</label>
                <select class="form-select form-select-sm" id="filterClient" name="client_id">
                    <option value="">All Clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->client_id }}" @selected(($filters['client_id'] ?? null) == $client->client_id)>
                            {{ $client->client_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>
                            {{ $batch->batch_code }}
                        </option>
                    @endforeach
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
                <a href="{{ route('inventory.stock-out') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stock Out Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Release History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="stockOutTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">STOCK ID</th>
                        <th>CLIENT</th>
                        <th>BATCH</th>
                        <th>QUANTITY</th>
                        <th>TOTAL AMOUNT</th>
                        <th>RELEASE DATE</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOuts as $stockOut)
                        @php
                            $details = [
                                'reference' => $stockOut->reference(),
                                'client' => $stockOut->client?->client_name ?? '—',
                                'contact_number' => $stockOut->client?->contact_number ?? '—',
                                'address' => $stockOut->client?->address ?? '—',
                                'batch_code' => $stockOut->batch?->batch_code ?? '—',
                                'variety' => $stockOut->batch?->variety?->variety_name ?? '—',
                                'quantity' => (int) $stockOut->quantity,
                                'total_amount' => $stockOut->total_amount,
                                'release_date' => $formatDate($stockOut->release_date),
                                'recorded_by' => $stockOut->user?->name ?? '—',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $stockOut->reference() }}</td>
                            <td>{{ $stockOut->client?->client_name ?? '—' }}</td>
                            <td>
                                <span class="fw-medium">{{ $stockOut->batch?->batch_code ?? '—' }}</span>
                                <div class="text-muted small">{{ $stockOut->batch?->variety?->variety_name ?? '—' }}</div>
                            </td>
                            <td><span class="badge bg-danger">{{ number_format((int) $stockOut->quantity) }}</span></td>
                            <td>{{ $formatAmount($stockOut->total_amount) }}</td>
                            <td>{{ $formatDate($stockOut->release_date) }}</td>
                            <td class="text-muted small">{{ $stockOut->user?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockOutDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No stock-out transactions found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $stockOuts->firstItem() ?? 0 }} to {{ $stockOuts->lastItem() ?? 0 }} of {{ $stockOuts->total() }} entries
        </span>
        {{ $stockOuts->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- New Stock Out Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-secondary">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">New Stock Out</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stockOutForm" method="POST" action="{{ route('inventory.stock-out.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label text-muted small fw-bold mb-0" for="stockOutClient">Client <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-sm p-0" id="switchToClientBtn">+ Add Client</button>
                        </div>
                        <select class="form-select @error('client_id', 'stockout') is-invalid @enderror" id="stockOutClient" name="client_id" required>
                            <option value="" disabled @selected(old('client_id') === null)>Select Client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->client_id }}" @selected(old('client_id', session('new_client_id')) == $client->client_id)>
                                    {{ $client->client_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_id', 'stockout')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="stockOutBatch">Batch (Source) <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'stockout') is-invalid @enderror" id="stockOutBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($batches as $batch)
                                @php $available = (int) ($batch->inventory?->current_quantity ?? $batch->available_count); @endphp
                                <option value="{{ $batch->batch_id }}" data-available="{{ $available }}" @selected(old('batch_id') == $batch->batch_id)>
                                    {{ $batch->batch_code }} — {{ $batch->variety?->variety_name ?? 'N/A' }} (Available: {{ number_format($available) }})
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'stockout')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockOutQuantity">Quantity to Release <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'stockout') is-invalid @enderror" id="stockOutQuantity"
                                name="quantity" min="1" step="1" required placeholder="e.g. 100" value="{{ old('quantity') }}">
                            <div class="form-text" id="availableHint"></div>
                            @error('quantity', 'stockout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockOutDate">Release Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('release_date', 'stockout') is-invalid @enderror" id="stockOutDate"
                                name="release_date" required max="{{ now()->toDateString() }}" value="{{ old('release_date', now()->toDateString()) }}">
                            @error('release_date', 'stockout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="stockOutAmount">Total Amount <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control @error('total_amount', 'stockout') is-invalid @enderror" id="stockOutAmount"
                                name="total_amount" placeholder="0.00" value="{{ old('total_amount') }}">
                            @error('total_amount', 'stockout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Recording stock-out decreases the batch's inventory balance and cannot exceed what is available. This cannot be edited later; use Stock Reversal to correct a transaction.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary" id="stockOutSubmitBtn">Save Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clientForm" method="POST" action="{{ route('inventory.clients.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientName">Client Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('client_name', 'client') is-invalid @enderror" id="clientName"
                            name="client_name" maxlength="255" required value="{{ old('client_name') }}">
                        @error('client_name', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'client') is-invalid @enderror" id="clientContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'client') is-invalid @enderror" id="clientAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary" id="clientSubmitBtn">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Out Details Modal -->
<div class="modal fade" id="stockOutDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Stock-Out Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Client:</div>
                    <div class="col-7" id="detailsClient">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Contact Number:</div>
                    <div class="col-7" id="detailsContact">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Address:</div>
                    <div class="col-7" id="detailsAddress">&mdash;</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Batch:</div>
                    <div class="col-7" id="detailsBatch">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Variety:</div>
                    <div class="col-7" id="detailsVariety">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Quantity:</div>
                    <div class="col-7 text-danger fw-bold" id="detailsQuantity">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Total Amount:</div>
                    <div class="col-7" id="detailsAmount">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Release Date:</div>
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
        const stockOutModalEl = document.getElementById('stockOutModal');
        const clientModalEl = document.getElementById('clientModal');

        @if (session('new_client_id'))
            new bootstrap.Modal(stockOutModalEl).show();
        @elseif ($errors->stockout->any())
            new bootstrap.Modal(stockOutModalEl).show();
        @elseif ($errors->client->any())
            new bootstrap.Modal(clientModalEl).show();
        @endif

        const switchBtn = document.getElementById('switchToClientBtn');
        if (switchBtn) {
            switchBtn.addEventListener('click', function () {
                bootstrap.Modal.getInstance(stockOutModalEl)?.hide();
                new bootstrap.Modal(clientModalEl).show();
            });
        }

        const batchSelect = document.getElementById('stockOutBatch');
        const quantityInput = document.getElementById('stockOutQuantity');
        const availableHint = document.getElementById('availableHint');

        const refreshAvailable = function () {
            if (!batchSelect || !quantityInput || !availableHint) {
                return;
            }

            const option = batchSelect.options[batchSelect.selectedIndex];
            const available = option ? parseInt(option.getAttribute('data-available') || '0', 10) : 0;

            if (batchSelect.value) {
                quantityInput.setAttribute('max', available);
                availableHint.textContent = 'Available to release: ' + available.toLocaleString() + ' trees.';
            } else {
                quantityInput.removeAttribute('max');
                availableHint.textContent = '';
            }
        };

        if (batchSelect) {
            batchSelect.addEventListener('change', refreshAvailable);
            refreshAvailable();
        }

        const detailsModal = document.getElementById('stockOutDetailsModal');
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
                setText('detailsClient', details.client);
                setText('detailsContact', details.contact_number);
                setText('detailsAddress', details.address);
                setText('detailsBatch', details.batch_code);
                setText('detailsVariety', details.variety);
                setText('detailsQuantity', Number(details.quantity || 0).toLocaleString());
                setText('detailsAmount', details.total_amount == null || details.total_amount === ''
                    ? '—'
                    : '₱' + Number(details.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                setText('detailsDate', details.release_date);
                setText('detailsRecordedBy', details.recorded_by);
            });
        }

        const guardSubmit = function (formId, buttonId, label) {
            const form = document.getElementById(formId);
            const button = document.getElementById(buttonId);
            if (!form || !button) {
                return;
            }

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + label;
            });
        };

        guardSubmit('stockOutForm', 'stockOutSubmitBtn', 'Saving...');
        guardSubmit('clientForm', 'clientSubmitBtn', 'Saving...');
    });
</script>
@endpush
