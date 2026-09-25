@extends('layouts.app')

@section('title', 'Stock In')
@section('page_title', 'Stock In')

@section('page_actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="bi bi-truck me-1"></i> New Supplier
    </button>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockInModal">
        <i class="bi bi-plus-lg me-1"></i> New Stock In
    </button>
</div>
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
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Quantity Received</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalReceived) }} <span class="fs-6 fw-normal text-muted">seedlings</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Viable</div>
                <div class="fs-3 fw-bold">{{ number_format($totalViable) }} <span class="fs-6 fw-normal text-muted">seedlings</span></div>
                <div class="text-muted small">Usable after the five-day count</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-In Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalTransactions) }}</div>
                <div class="text-muted small">Receiving records in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Suppliers Involved</div>
                <div class="fs-3 fw-bold">{{ number_format($totalSuppliers) }}</div>
                <div class="text-muted small">Distinct suppliers in the current filters</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.stock-in') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Supplier or origin...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterSupplier" class="form-label text-muted small fw-bold mb-1">Supplier</label>
                <select class="form-select form-select-sm" id="filterSupplier" name="supplier_id">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->supplier_id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->supplier_id)>
                            {{ $supplier->supplier_name }}
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
                <a href="{{ route('inventory.stock-in') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stock In Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Receiving History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="stockInTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">STOCK ID</th>
                        <th>SUPPLIER</th>
                        <th class="text-end">QUANTITY</th>
                        <th class="text-end">VIABLE</th>
                        <th>ORIGIN</th>
                        <th>DATE RECEIVED</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockIns as $stockIn)
                        @php
                            $details = [
                                'reference' => $stockIn->reference(),
                                'supplier' => $stockIn->supplier?->supplier_name ?? '—',
                                'contact_number' => $stockIn->supplier?->contact_number ?? '—',
                                'address' => $stockIn->supplier?->address ?? '—',
                                'quantity' => (int) $stockIn->quantity,
                                'viable' => (int) $stockIn->viable,
                                'origin' => $stockIn->origin ?: '—',
                                'date_received' => $formatDate($stockIn->date_received),
                                'recorded_by' => $stockIn->user?->name ?? '—',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $stockIn->reference() }}</td>
                            <td>{{ $stockIn->supplier?->supplier_name ?? '—' }}</td>
                            <td class="text-end"><span class="badge bg-success">{{ number_format((int) $stockIn->quantity) }}</span></td>
                            <td class="text-end"><span class="badge bg-primary">{{ number_format((int) $stockIn->viable) }}</span></td>
                            <td>{{ $stockIn->origin ?: '—' }}</td>
                            <td>{{ $formatDate($stockIn->date_received) }}</td>
                            <td class="text-muted small">{{ $stockIn->user?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockInDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No stock-in transactions found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $stockIns->firstItem() ?? 0 }} to {{ $stockIns->lastItem() ?? 0 }} of {{ $stockIns->total() }} entries
        </span>
        {{ $stockIns->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- New Stock In Modal -->
<div class="modal fade" id="stockInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">New Stock In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stockInForm" method="POST" action="{{ route('inventory.stock-in.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label text-muted small fw-bold mb-0" for="stockInSupplier">Supplier <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-sm p-0" id="switchToSupplierBtn">+ Add Supplier</button>
                        </div>
                        <select class="form-select @error('supplier_id', 'stockin') is-invalid @enderror" id="stockInSupplier" name="supplier_id" required>
                            <option value="" disabled @selected(old('supplier_id') === null)>Select Supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}" @selected(old('supplier_id', session('new_supplier_id')) == $supplier->supplier_id)>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id', 'stockin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInQuantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'stockin') is-invalid @enderror" id="stockInQuantity"
                                name="quantity" min="1" step="1" required placeholder="e.g. 500" value="{{ old('quantity') }}">
                            @error('quantity', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">The ordered/received quantity.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInViable">Viable <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('viable', 'stockin') is-invalid @enderror" id="stockInViable"
                                name="viable" min="0" step="1" required placeholder="e.g. 460" value="{{ old('viable') }}">
                            @error('viable', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-danger d-none" id="viableHint">Viable count cannot exceed the ordered quantity.</div>
                            <div class="form-text">Usable count after the five-day recount.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInOrigin">Origin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('origin', 'stockin') is-invalid @enderror" id="stockInOrigin"
                                name="origin" maxlength="255" required placeholder="e.g. Batangas" value="{{ old('origin') }}">
                            @error('origin', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInDate">Date Received <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date_received', 'stockin') is-invalid @enderror" id="stockInDate"
                                name="date_received" required max="{{ now()->toDateString() }}" value="{{ old('date_received', now()->toDateString()) }}">
                            @error('date_received', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Stock In records the usable seedlings after the five-day count. It is not linked to a batch — batches are
                        established from the viable seedlings afterwards.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="stockInSubmitBtn">Save Stock In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="supplierForm" method="POST" action="{{ route('inventory.suppliers.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="supplierName">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('supplier_name', 'supplier') is-invalid @enderror" id="supplierName"
                            name="supplier_name" maxlength="255" required value="{{ old('supplier_name') }}">
                        @error('supplier_name', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="supplierContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'supplier') is-invalid @enderror" id="supplierContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="supplierAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'supplier') is-invalid @enderror" id="supplierAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="supplierSubmitBtn">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock In Details Modal -->
<div class="modal fade" id="stockInDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Stock-In Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Supplier:</div>
                    <div class="col-7" id="detailsSupplier">&mdash;</div>
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
                    <div class="col-5 text-muted small fw-bold">Quantity:</div>
                    <div class="col-7 text-success fw-bold" id="detailsQuantity">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Viable:</div>
                    <div class="col-7 text-primary fw-bold" id="detailsViable">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Origin:</div>
                    <div class="col-7" id="detailsOrigin">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Date Received:</div>
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
        const stockInModalEl = document.getElementById('stockInModal');
        const supplierModalEl = document.getElementById('supplierModal');

        @if (session('new_supplier_id'))
            new bootstrap.Modal(stockInModalEl).show();
        @elseif ($errors->stockin->any())
            new bootstrap.Modal(stockInModalEl).show();
        @elseif ($errors->supplier->any())
            new bootstrap.Modal(supplierModalEl).show();
        @endif

        const switchBtn = document.getElementById('switchToSupplierBtn');
        if (switchBtn) {
            switchBtn.addEventListener('click', function () {
                bootstrap.Modal.getInstance(stockInModalEl)?.hide();
                new bootstrap.Modal(supplierModalEl).show();
            });
        }

        // Client-side validation layer: viable may never exceed the ordered quantity.
        const quantityInput = document.getElementById('stockInQuantity');
        const viableInput = document.getElementById('stockInViable');
        const viableHint = document.getElementById('viableHint');

        const validateViable = function () {
            if (!quantityInput || !viableInput || !viableHint) {
                return true;
            }

            const quantity = parseInt(quantityInput.value || '0', 10);
            const viable = parseInt(viableInput.value || '0', 10);
            const invalid = quantityInput.value !== '' && viableInput.value !== '' && viable > quantity;

            viableInput.setCustomValidity(invalid ? 'Viable count cannot exceed the ordered quantity.' : '');
            viableInput.classList.toggle('is-invalid', invalid);
            viableHint.classList.toggle('d-none', !invalid);

            return !invalid;
        };

        if (quantityInput && viableInput) {
            quantityInput.addEventListener('input', validateViable);
            viableInput.addEventListener('input', validateViable);
        }

        const stockInForm = document.getElementById('stockInForm');
        if (stockInForm) {
            stockInForm.addEventListener('submit', function (event) {
                if (!validateViable()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            });
        }

        const detailsModal = document.getElementById('stockInDetailsModal');
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
                setText('detailsSupplier', details.supplier);
                setText('detailsContact', details.contact_number);
                setText('detailsAddress', details.address);
                setText('detailsQuantity', Number(details.quantity || 0).toLocaleString());
                setText('detailsViable', Number(details.viable || 0).toLocaleString());
                setText('detailsOrigin', details.origin);
                setText('detailsDate', details.date_received);
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

        guardSubmit('stockInForm', 'stockInSubmitBtn', 'Saving...');
        guardSubmit('supplierForm', 'supplierSubmitBtn', 'Saving...');
    });
</script>
@endpush
