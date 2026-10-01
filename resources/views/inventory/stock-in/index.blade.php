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
                <div class="fs-3 fw-bold text-success">{{ number_format($totalReceived) }} <span class="fs-6 fw-normal text-muted">units</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Received (seedlings)</div>
                <div class="fs-3 fw-bold">{{ number_format($totalReceivedSeedlings) }} <span class="fs-6 fw-normal text-muted">units</span></div>
                <div class="text-muted small">Seedling-only quantity in the current filters</div>
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
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Supplier or origin...">
                </div>
            </div>
            <div class="col-md-2">
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
                <label for="filterType" class="form-label text-muted small fw-bold mb-1">Type</label>
                <select class="form-select form-select-sm" id="filterType" name="type">
                    <option value="">All Types</option>
                    <option value="seedling" @selected(($filters['type'] ?? null) === 'seedling')>Seedlings</option>
                    <option value="scion" @selected(($filters['type'] ?? null) === 'scion')>Scion</option>
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
                        <th>TYPE</th>
                        <th>SUPPLIER</th>
                        <th>SCION VARIETY</th>
                        <th class="text-end">QUANTITY</th>
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
                            'type' => $stockIn->entryTypeLabel(),
                            'supplier' => $stockIn->supplier?->supplier_name ?? '—',
                                'contact_number' => $stockIn->supplier?->contact_number ?? '—',
                                'address' => $stockIn->supplier?->address ?? '—',
                                'scion_variety' => $stockIn->scionVariety?->scion_variety_name ?? '—',
                                'unit_price' => $stockIn->unit_price !== null
                                    ? '₱'.number_format((float) $stockIn->unit_price, 2)
                                    : '—',
                                'quantity' => (int) $stockIn->quantity,
                                'origin' => $stockIn->origin ?: '—',
                                'notes' => $stockIn->notes ?: '—',
                                'date_received' => $formatDate($stockIn->date_received),
                                'recorded_by' => $stockIn->user?->name ?? '—',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">
                                {{ $stockIn->reference() }}
                            </td>
                            <td>
                                @if ($stockIn->isScionEntry())
                                    <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-flower1 me-1"></i>Scion</span>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-tree me-1"></i>Seedlings</span>
                                @endif
                            </td>
                            <td>{{ $stockIn->supplier?->supplier_name ?? '—' }}</td>
                            <td>
                                @if ($stockIn->isScionEntry())
                                    {{ $stockIn->scionVariety?->scion_variety_name ?? '—' }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end"><span class="badge bg-success">{{ number_format((int) $stockIn->quantity) }}</span></td>
                            <td>{{ $stockIn->origin ?: '—' }}</td>
                            <td>{{ $formatDate($stockIn->date_received) }}</td>
                            <td class="text-muted small">{{ $stockIn->user?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockInDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                @if (auth()->user()->hasPermission('correct_records'))
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#correctStockInModal"
                                        data-action="{{ route('corrections.stock-in', $stockIn) }}"
                                        data-supplier-id="{{ $stockIn->supplier_id }}"
                                        data-scion-variety-id="{{ $stockIn->scion_variety_id }}"
                                        data-quantity="{{ (int) $stockIn->quantity }}"
                                        data-unit-price="{{ $stockIn->unit_price }}"
                                        data-origin="{{ $stockIn->origin }}"
                                        data-notes="{{ $stockIn->notes }}"
                                        data-date-received="{{ $stockIn->date_received?->toDateString() }}"
                                        title="Correct this receiving">
                                        <i class="bi bi-pencil"></i> Correct
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
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
                    @php
                        $activeEntry = old('scion_variety_id') ? 'scion' : 'seedling';
                    @endphp

                    <!--
                        Two tabs, one form, one table (stock_ins) — reflects the client's
                        confirmed single real-world event while keeping data entry unambiguous
                        for staff (ERD note; migration
                        2026_09_26_000003_add_scion_columns_to_stock_ins_table). Supplier,
                        quantity, origin and date received are shared because they apply to
                        either kind of receiving; the tabs only switch the mode-specific fields
                        — "Seedlings" records a plain seedling delivery, "Scion" adds the scion
                        variety + unit price. The inactive tab's fields are disabled so only
                        the chosen entry type is submitted.
                    -->
                    <ul class="nav nav-tabs mb-3" id="stockInTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeEntry === 'seedling' ? 'active' : '' }}" id="seedlingsTab" data-bs-toggle="tab"
                                data-bs-target="#seedlingsPane" data-entry-type="seedling" type="button" role="tab"
                                aria-controls="seedlingsPane" aria-selected="{{ $activeEntry === 'seedling' ? 'true' : 'false' }}">
                                <i class="bi bi-tree me-1"></i> Seedlings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeEntry === 'scion' ? 'active' : '' }}" id="scionTab" data-bs-toggle="tab"
                                data-bs-target="#scionPane" data-entry-type="scion" type="button" role="tab"
                                aria-controls="scionPane" aria-selected="{{ $activeEntry === 'scion' ? 'true' : 'false' }}">
                                <i class="bi bi-flower1 me-1"></i> Scion
                            </button>
                        </li>
                    </ul>

                    <!-- Shared receiving fields (apply to both seedlings and scion) -->
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
                            <div class="form-text">The received quantity.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInOrigin">Origin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('origin', 'stockin') is-invalid @enderror" id="stockInOrigin"
                                name="origin" maxlength="255" required placeholder="e.g. Batangas" value="{{ old('origin') }}">
                            @error('origin', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockInDate">Date Received <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date_received', 'stockin') is-invalid @enderror" id="stockInDate"
                                name="date_received" required max="{{ now()->toDateString() }}" value="{{ old('date_received', now()->toDateString()) }}">
                            @error('date_received', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade {{ $activeEntry === 'seedling' ? 'show active' : '' }}" id="seedlingsPane" role="tabpanel" aria-labelledby="seedlingsTab">
                            <div class="alert alert-light border small mb-0" id="seedlingInfo">
                                <i class="bi bi-info-circle me-1"></i>
                                Stock In records the seedlings received immediately. It is not linked to a batch — batches are
                                established from the received seedlings afterwards.
                            </div>
                        </div>

                        <div class="tab-pane fade {{ $activeEntry === 'scion' ? 'show active' : '' }}" id="scionPane" role="tabpanel" aria-labelledby="scionTab">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold" for="stockInScionVariety">Scion Variety <span class="text-danger">*</span></label>
                                <select class="form-select @error('scion_variety_id', 'stockin') is-invalid @enderror" id="stockInScionVariety" name="scion_variety_id" required>
                                    <option value="" disabled {{ old('scion_variety_id') ? '' : 'selected' }}>Select a scion variety...</option>
                                    @foreach ($scionVarieties as $scionVariety)
                                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(old('scion_variety_id') == $scionVariety->scion_variety_id)>
                                            {{ $scionVariety->scion_variety_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('scion_variety_id', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold" for="stockInUnitPrice">Unit Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('unit_price', 'stockin') is-invalid @enderror" id="stockInUnitPrice"
                                    name="unit_price" min="0" step="0.01" required placeholder="e.g. 12.50" value="{{ old('unit_price') }}">
                                @error('unit_price', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label text-muted small fw-bold" for="stockInNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control @error('notes', 'stockin') is-invalid @enderror" id="stockInNotes" name="notes"
                                    rows="2" maxlength="1000" placeholder="e.g. Premium bundle">{{ old('notes') }}</textarea>
                                @error('notes', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="alert alert-light border small mb-0" id="scionInfo">
                                <i class="bi bi-info-circle me-1"></i>
                                Scion receiving is recorded as a Stock In row with a scion variety and unit price.
                            </div>
                        </div>
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
                    <div class="col-5 text-muted small fw-bold">Type:</div>
                    <div class="col-7" id="detailsType">&mdash;</div>
                </div>
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
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Scion Variety:</div>
                    <div class="col-7" id="detailsScionVariety">&mdash;</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Quantity:</div>
                    <div class="col-7 text-success fw-bold" id="detailsQuantity">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Unit Price:</div>
                    <div class="col-7" id="detailsUnitPrice">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Origin:</div>
                    <div class="col-7" id="detailsOrigin">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Notes:</div>
                    <div class="col-7" id="detailsNotes">&mdash;</div>
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

@if (auth()->user()->hasPermission('correct_records'))
<!-- Correct Stock In Modal (administrator only — OD-5) -->
<div class="modal fade" id="correctStockInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>Correct Stock-In Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="correctStockInForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        The corrected receiving is validated exactly like a new entry (a scion variety and a unit price go
                        together, or neither does) and the before/after values are recorded in Activity History.
                    </p>
                    <div class="mb-3">
                        <label for="correctStockInSupplier" class="form-label text-muted small fw-bold">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select @error('supplier_id', 'stockin_correction') is-invalid @enderror" id="correctStockInSupplier" name="supplier_id" required>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}">{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id', 'stockin_correction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="correctStockInQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'stockin_correction') is-invalid @enderror" id="correctStockInQuantity"
                                name="quantity" min="1" required>
                            @error('quantity', 'stockin_correction')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="correctStockInDate" class="form-label text-muted small fw-bold">Date Received <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date_received', 'stockin_correction') is-invalid @enderror" id="correctStockInDate"
                                name="date_received" max="{{ now()->toDateString() }}" required>
                            @error('date_received', 'stockin_correction')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="correctStockInVariety" class="form-label text-muted small fw-bold">Scion Variety</label>
                            <select class="form-select @error('scion_variety_id', 'stockin_correction') is-invalid @enderror" id="correctStockInVariety" name="scion_variety_id">
                                <option value="">— Seedling only —</option>
                                @foreach ($scionVarieties as $scionVariety)
                                    <option value="{{ $scionVariety->scion_variety_id }}">{{ $scionVariety->scion_variety_name }}</option>
                                @endforeach
                            </select>
                            @error('scion_variety_id', 'stockin_correction')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="correctStockInUnitPrice" class="form-label text-muted small fw-bold">Unit Price</label>
                            <input type="number" step="0.01" min="0" class="form-control @error('unit_price', 'stockin_correction') is-invalid @enderror" id="correctStockInUnitPrice"
                                name="unit_price">
                            @error('unit_price', 'stockin_correction')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="correctStockInOrigin" class="form-label text-muted small fw-bold">Origin <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('origin', 'stockin_correction') is-invalid @enderror" id="correctStockInOrigin"
                            name="origin" maxlength="255" required>
                        @error('origin', 'stockin_correction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="correctStockInNotes" class="form-label text-muted small fw-bold">Notes</label>
                        <textarea class="form-control @error('notes', 'stockin_correction') is-invalid @enderror" id="correctStockInNotes"
                            name="notes" rows="2" maxlength="1000"></textarea>
                        @error('notes', 'stockin_correction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Save Correction</button>
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
        const stockInModalEl = document.getElementById('stockInModal');
        const supplierModalEl = document.getElementById('supplierModal');

        @if (session('new_supplier_id'))
            new bootstrap.Modal(stockInModalEl).show();
        @elseif ($errors->stockin->any())
            new bootstrap.Modal(stockInModalEl).show();
        @elseif ($errors->supplier->any())
            new bootstrap.Modal(supplierModalEl).show();
        @endif

        // Two-tab entry: "Seedlings" and "Scion" share one form/one submit action,
        // but only the active tab's mode-specific fields are enabled, so the
        // payload is never ambiguous (a plain seedling receiving vs
        // scion_variety_id + unit_price for a scion receiving). Mirrors the
        // conditional validation in StoreStockInRequest.
        const entryFields = {
            seedling: [],
            scion: ['stockInScionVariety', 'stockInUnitPrice', 'stockInNotes'],
        };
        const seedlingInfo = document.getElementById('seedlingInfo');
        const scionInfo = document.getElementById('scionInfo');

        const setEntryType = function (type) {
            const isScion = type === 'scion';

            entryFields.seedling.forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = isScion;
                }
            });

            entryFields.scion.forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = !isScion;
                }
            });

            if (seedlingInfo) {
                seedlingInfo.classList.toggle('d-none', isScion);
            }
            if (scionInfo) {
                scionInfo.classList.toggle('d-none', !isScion);
            }
        };

        document.querySelectorAll('#stockInTabs [data-entry-type]').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function (event) {
                setEntryType(event.target.getAttribute('data-entry-type'));
            });
        });

        const initialEntryTab = document.querySelector('#stockInTabs .nav-link.active');
        setEntryType(initialEntryTab ? initialEntryTab.getAttribute('data-entry-type') : 'seedling');

        const switchBtn = document.getElementById('switchToSupplierBtn');
        if (switchBtn) {
            switchBtn.addEventListener('click', function () {
                bootstrap.Modal.getInstance(stockInModalEl)?.hide();
                new bootstrap.Modal(supplierModalEl).show();
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
                setText('detailsType', details.type);
                setText('detailsSupplier', details.supplier);
                setText('detailsContact', details.contact_number);
                setText('detailsAddress', details.address);
                setText('detailsScionVariety', details.scion_variety);
                setText('detailsQuantity', Number(details.quantity || 0).toLocaleString());
                setText('detailsUnitPrice', details.unit_price);
                setText('detailsOrigin', details.origin);
                setText('detailsNotes', details.notes);
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

        @if ($errors->stockin_correction->any())
            const correctStockInErrorEl = document.getElementById('correctStockInModal');
            if (correctStockInErrorEl) {
                new bootstrap.Modal(correctStockInErrorEl).show();
            }
        @endif

        const correctStockInModalEl = document.getElementById('correctStockInModal');
        if (correctStockInModalEl) {
            correctStockInModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                document.getElementById('correctStockInForm').action = button.getAttribute('data-action');
                document.getElementById('correctStockInSupplier').value = button.getAttribute('data-supplier-id') || '';
                document.getElementById('correctStockInVariety').value = button.getAttribute('data-scion-variety-id') || '';
                document.getElementById('correctStockInUnitPrice').value = button.getAttribute('data-unit-price') || '';
                document.getElementById('correctStockInQuantity').value = button.getAttribute('data-quantity') || '';
                document.getElementById('correctStockInOrigin').value = button.getAttribute('data-origin') || '';
                document.getElementById('correctStockInNotes').value = button.getAttribute('data-notes') || '';
                document.getElementById('correctStockInDate').value = button.getAttribute('data-date-received') || '';
            });
        }
    });
</script>
@endpush
