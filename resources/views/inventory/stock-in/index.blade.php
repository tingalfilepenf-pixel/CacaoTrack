@extends('layouts.app')

@section('title', 'Stock In')
@section('page_title', 'Stock In')

@section('page_actions')
<div class="d-flex gap-2">
    @if (auth()->user()->hasPermission('manage_inventory'))
        <button type="button" class="btn btn-outline-secondary js-add-supplier" data-supplier-target="" data-bs-toggle="modal" data-bs-target="#supplierModal">
            <i class="bi bi-truck me-1"></i> New Supplier
        </button>
    @endif
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
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3 mb-4">
    <div class="col">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Quantity Received</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalReceived) }} <span class="fs-6 fw-normal text-muted">units</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Received (seedlings)</div>
                <div class="fs-3 fw-bold">{{ number_format($totalReceivedSeedlings) }} <span class="fs-6 fw-normal text-muted">units</span></div>
                <div class="text-muted small">Seedling-only quantity in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Received (Scion)</div>
                <div class="fs-3 fw-bold">{{ number_format($totalReceivedScion) }} <span class="fs-6 fw-normal text-muted">units</span></div>
                <div class="text-muted small">Scion-only quantity in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-In Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalTransactions) }}</div>
                <div class="text-muted small">Receiving records in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col">
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
        <form method="GET" action="{{ route('inventory.stock-in') }}" class="row g-3 align-items-end" id="stockInFilterForm" data-auto-filter>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <a href="{{ route('inventory.stock-in') }}" class="btn btn-sm btn-outline-secondary w-100" title="Clear all filters">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Clear Filters
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <!-- The form IS the .modal-content: a form wrapping body + footer broke
             the scrollable dialog and pushed the footer off-screen. -->
        <form id="stockInForm" method="POST" action="{{ route('inventory.stock-in.store') }}" class="modal-content border-primary">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">New Stock In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @php
                    $stockInErrorKeys = collect($errors->stockin->keys());
                    $seedlingHasErrors = $stockInErrorKeys->contains(fn ($key) => str_starts_with($key, 'seedling.'));
                    $scionHasErrors = $stockInErrorKeys->contains(fn ($key) => str_starts_with($key, 'scion.'));

                    $seedlingOld = (array) old('seedling', []);
                    $scionOld = (array) old('scion', []);
                    $seedlingFilled = collect(['supplier_id', 'quantity', 'origin', 'notes'])
                        ->contains(fn ($key) => filled($seedlingOld[$key] ?? null));
                    $scionFilled = collect(['supplier_id', 'quantity', 'origin', 'scion_variety_id', 'unit_price', 'notes'])
                        ->contains(fn ($key) => filled($scionOld[$key] ?? null));

                    // Reopen the first tab that has errors; otherwise keep the tab
                    // the user was working in when only one entry was filled.
                    $activeEntry = $seedlingHasErrors
                        ? 'seedling'
                        : ($scionHasErrors
                            ? 'scion'
                            : ($scionFilled && ! $seedlingFilled ? 'scion' : 'seedling'));
                @endphp

                <!--
                    Two independent entries share one form and one table (stock_ins).
                    Each tab posts its own nested fields (seedling[...] / scion[...]),
                    both stay enabled, and the server decides which entries are filled.
                    One footer Save button submits both in a single transaction.
                -->
                <ul class="nav nav-tabs mb-3" id="stockInTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeEntry === 'seedling' ? 'active' : '' }}" id="seedlingsTab" data-bs-toggle="tab"
                            data-bs-target="#seedlingsPane" type="button" role="tab"
                            aria-controls="seedlingsPane" aria-selected="{{ $activeEntry === 'seedling' ? 'true' : 'false' }}">
                            <i class="bi bi-tree me-1"></i> Seedlings
                            <span class="badge rounded-pill bg-success ms-1 {{ $seedlingFilled ? '' : 'd-none' }}" data-tab-filled="seedling">Filled</span>
                            <span class="badge rounded-pill bg-danger ms-1 {{ $seedlingHasErrors ? '' : 'd-none' }}" data-tab-error="seedling" title="This tab needs attention">!</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeEntry === 'scion' ? 'active' : '' }}" id="scionTab" data-bs-toggle="tab"
                            data-bs-target="#scionPane" type="button" role="tab"
                            aria-controls="scionPane" aria-selected="{{ $activeEntry === 'scion' ? 'true' : 'false' }}">
                            <i class="bi bi-flower1 me-1"></i> Scion
                            <span class="badge rounded-pill bg-success ms-1 {{ $scionFilled ? '' : 'd-none' }}" data-tab-filled="scion">Filled</span>
                            <span class="badge rounded-pill bg-danger ms-1 {{ $scionHasErrors ? '' : 'd-none' }}" data-tab-error="scion" title="This tab needs attention">!</span>
                        </button>
                    </li>
                </ul>

                @error('entries', 'stockin')
                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                @enderror
                @error('submission', 'stockin')
                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                @enderror

                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeEntry === 'seedling' ? 'show active' : '' }}" id="seedlingsPane" role="tabpanel" aria-labelledby="seedlingsTab">
                        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                            <div class="fw-bold text-success"><i class="bi bi-tree me-1"></i>Seedlings entry</div>
                            <div class="text-muted small">Required when you fill in this tab.</div>
                        </div>
                        <div class="alert alert-light border small py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Seedlings are recorded without a variety or price.
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label text-muted small fw-bold" for="seedlingSupplier">Supplier <span class="text-danger">*</span></label>
                                @if (auth()->user()->hasPermission('manage_inventory'))
                                    <button type="button" class="btn btn-sm btn-link p-0 js-add-supplier" data-supplier-target="seedling"
                                        data-bs-toggle="modal" data-bs-target="#supplierModal">+ Add Supplier</button>
                                @endif
                            </div>
                            <select class="form-select @error('seedling.supplier_id', 'stockin') is-invalid @enderror" id="seedlingSupplier" name="seedling[supplier_id]">
                                <option value="" @selected((string) old('seedling.supplier_id', '') === '')>Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->supplier_id }}" @selected((string) old('seedling.supplier_id') === (string) $supplier->supplier_id)>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('seedling.supplier_id', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless (auth()->user()->hasPermission('manage_inventory'))
                                <div class="form-text">Not listed? Ask a Manager to add it.</div>
                            @endunless
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="seedlingQuantity">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('seedling.quantity', 'stockin') is-invalid @enderror" id="seedlingQuantity"
                                    name="seedling[quantity]" min="1" step="1" inputmode="numeric" placeholder="e.g. 500" value="{{ old('seedling.quantity') }}">
                                @error('seedling.quantity', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Whole units received.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="seedlingOrigin">Origin <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('seedling.origin', 'stockin') is-invalid @enderror" id="seedlingOrigin"
                                    name="seedling[origin]" maxlength="255" placeholder="e.g. Batangas" value="{{ old('seedling.origin') }}">
                                @error('seedling.origin', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="seedlingDate">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('seedling.date_received', 'stockin') is-invalid @enderror" id="seedlingDate"
                                    name="seedling[date_received]" max="{{ now()->toDateString() }}" value="{{ old('seedling.date_received', now()->toDateString()) }}">
                                @error('seedling.date_received', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="seedlingNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control @error('seedling.notes', 'stockin') is-invalid @enderror" id="seedlingNotes" name="seedling[notes]"
                                rows="2" maxlength="1000" placeholder="e.g. Premium bundle">{{ old('seedling.notes') }}</textarea>
                            @error('seedling.notes', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <button type="button" class="btn btn-link btn-sm p-0" data-clear-entry="seedling">Clear this entry</button>
                            <button type="button" class="btn btn-link btn-sm p-0" data-copy-entry="seedling">Copy supplier and date from the other tab</button>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeEntry === 'scion' ? 'show active' : '' }}" id="scionPane" role="tabpanel" aria-labelledby="scionTab">
                        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                            <div class="fw-bold text-info-emphasis"><i class="bi bi-flower1 me-1"></i>Scion entry</div>
                            <div class="text-muted small">Required when you fill in this tab.</div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label text-muted small fw-bold" for="scionSupplier">Supplier <span class="text-danger">*</span></label>
                                @if (auth()->user()->hasPermission('manage_inventory'))
                                    <button type="button" class="btn btn-sm btn-link p-0 js-add-supplier" data-supplier-target="scion"
                                        data-bs-toggle="modal" data-bs-target="#supplierModal">+ Add Supplier</button>
                                @endif
                            </div>
                            <select class="form-select @error('scion.supplier_id', 'stockin') is-invalid @enderror" id="scionSupplier" name="scion[supplier_id]">
                                <option value="" @selected((string) old('scion.supplier_id', '') === '')>Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->supplier_id }}" @selected((string) old('scion.supplier_id') === (string) $supplier->supplier_id)>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('scion.supplier_id', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless (auth()->user()->hasPermission('manage_inventory'))
                                <div class="form-text">Not listed? Ask a Manager to add it.</div>
                            @endunless
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="scionQuantity">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('scion.quantity', 'stockin') is-invalid @enderror" id="scionQuantity"
                                    name="scion[quantity]" min="1" step="1" inputmode="numeric" placeholder="e.g. 300" value="{{ old('scion.quantity') }}">
                                @error('scion.quantity', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Whole units received.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="scionOrigin">Origin <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('scion.origin', 'stockin') is-invalid @enderror" id="scionOrigin"
                                    name="scion[origin]" maxlength="255" placeholder="e.g. Batangas" value="{{ old('scion.origin') }}">
                                @error('scion.origin', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold" for="scionDate">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('scion.date_received', 'stockin') is-invalid @enderror" id="scionDate"
                                    name="scion[date_received]" max="{{ now()->toDateString() }}" value="{{ old('scion.date_received', now()->toDateString()) }}">
                                @error('scion.date_received', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold" for="scionVariety">Scion Variety <span class="text-danger">*</span></label>
                                <select class="form-select @error('scion.scion_variety_id', 'stockin') is-invalid @enderror" id="scionVariety" name="scion[scion_variety_id]">
                                    <option value="" @selected((string) old('scion.scion_variety_id', '') === '')>Select a scion variety...</option>
                                    @foreach ($scionVarieties as $scionVariety)
                                        <option value="{{ $scionVariety->scion_variety_id }}" @selected((string) old('scion.scion_variety_id') === (string) $scionVariety->scion_variety_id)>
                                            {{ $scionVariety->scion_variety_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('scion.scion_variety_id', 'stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold" for="scionUnitPrice">Unit Price <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control @error('scion.unit_price', 'stockin') is-invalid @enderror" id="scionUnitPrice"
                                        name="scion[unit_price]" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 12.50" value="{{ old('scion.unit_price') }}">
                                    @error('scion.unit_price', 'stockin')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Price per scion; enter 0 if there is no charge.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center rounded border bg-light px-3 py-2">
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Calculated Amount</div>
                                    <div class="form-text mt-0">Quantity &times; Unit Price (display only)</div>
                                </div>
                                <span class="fs-4 fw-bold text-primary" id="stockInCalculatedAmount" aria-live="polite">₱0.00</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="scionNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control @error('scion.notes', 'stockin') is-invalid @enderror" id="scionNotes" name="scion[notes]"
                                rows="2" maxlength="1000" placeholder="e.g. Premium bundle">{{ old('scion.notes') }}</textarea>
                            @error('scion.notes', 'stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <button type="button" class="btn btn-link btn-sm p-0" data-clear-entry="scion">Clear this entry</button>
                            <button type="button" class="btn btn-link btn-sm p-0" data-copy-entry="scion">Copy supplier and date from the other tab</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-top bg-light px-3 py-2 flex-shrink-0" id="stockInSaveSummaryBar">
                <div class="small text-muted" id="stockInSaveSummary" aria-live="polite">Fill in at least one tab.</div>
            </div>
            <div class="modal-footer flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="stockInSubmitBtn">Save Stock In</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="stockInDiscardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Discard what you entered?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Your Seedlings or Scion entry has not been saved yet.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep editing</button>
                <button type="button" class="btn btn-danger" id="stockInDiscardBtn">Discard</button>
            </div>
        </div>
    </div>
</div>

@if (auth()->user()->hasPermission('manage_inventory'))
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
@endif

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
        @elseif ($errors->supplier->any() && supplierModalEl)
            new bootstrap.Modal(supplierModalEl).show();
        @endif

        // Both tabs stay enabled at all times: the other tab's entry must never
        // be dropped by the browser. The server decides which entries count.
        const defaultStockInDate = @json(now()->toDateString());
        const fieldIds = {
            seedling: {
                supplier_id: 'seedlingSupplier',
                quantity: 'seedlingQuantity',
                origin: 'seedlingOrigin',
                date_received: 'seedlingDate',
                notes: 'seedlingNotes',
            },
            scion: {
                supplier_id: 'scionSupplier',
                quantity: 'scionQuantity',
                origin: 'scionOrigin',
                date_received: 'scionDate',
                scion_variety_id: 'scionVariety',
                unit_price: 'scionUnitPrice',
                notes: 'scionNotes',
            },
        };
        // The prefilled date never makes a tab "filled" / dirty.
        const dataFieldKeys = {
            seedling: ['supplier_id', 'quantity', 'origin', 'notes'],
            scion: ['supplier_id', 'quantity', 'origin', 'scion_variety_id', 'unit_price', 'notes'],
        };
        const summaryEl = document.getElementById('stockInSaveSummary');
        const calculatedAmountEl = document.getElementById('stockInCalculatedAmount');

        const getField = function (tab, key) {
            return document.getElementById(fieldIds[tab][key]);
        };

        const hasEntryData = function (tab) {
            return (dataFieldKeys[tab] || []).some(function (key) {
                const el = getField(tab, key);

                return el ? String(el.value || '').trim() !== '' : false;
            });
        };

        const entryQuantity = function (tab) {
            const el = getField(tab, 'quantity');
            const value = el ? Number(el.value) : NaN;

            return el && el.value !== '' && Number.isFinite(value) && value > 0
                ? value.toLocaleString()
                : null;
        };

        const formatPeso = function (value) {
            return '₱' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const refreshCalculatedAmount = function () {
            if (!calculatedAmountEl) {
                return;
            }

            const quantityInput = getField('scion', 'quantity');
            const unitPriceInput = getField('scion', 'unit_price');
            const quantity = quantityInput ? Number(quantityInput.value) : NaN;
            const unitPrice = unitPriceInput ? Number(unitPriceInput.value) : NaN;
            const hasQuantity = quantityInput && quantityInput.value !== '' && Number.isFinite(quantity) && quantity >= 0;
            const hasUnitPrice = unitPriceInput && unitPriceInput.value !== '' && Number.isFinite(unitPrice) && unitPrice >= 0;

            calculatedAmountEl.textContent = hasQuantity && hasUnitPrice
                ? formatPeso(quantity * unitPrice)
                : '₱0.00';
        };

        const refreshEntryHelpers = function () {
            Object.keys(dataFieldKeys).forEach(function (tab) {
                const filled = hasEntryData(tab);
                document.querySelectorAll('[data-tab-filled="' + tab + '"]').forEach(function (mark) {
                    mark.classList.toggle('d-none', !filled);
                });
            });

            const parts = [];
            ['seedling', 'scion'].forEach(function (tab) {
                if (!hasEntryData(tab)) {
                    return;
                }

                const label = tab === 'seedling' ? 'Seedlings' : 'Scion';
                const quantity = entryQuantity(tab);
                parts.push(quantity ? label + ' ' + quantity : label);
            });

            if (summaryEl) {
                summaryEl.textContent = parts.length === 0
                    ? 'Fill in at least one tab.'
                    : 'Will save: ' + parts.join(' and ');
            }

            refreshCalculatedAmount();
        };

        const clearPane = function (tab) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el) {
                    el.value = key === 'date_received' ? defaultStockInDate : '';
                }
            });
        };

        const snapshotPane = function (tab) {
            const snapshot = {};
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                snapshot[key] = el ? el.value : '';
            });

            return snapshot;
        };

        const restorePane = function (tab, values) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el && typeof values[key] === 'string') {
                    el.value = values[key];
                }
            });
        };

        Object.keys(fieldIds).forEach(function (tab) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el) {
                    el.addEventListener('input', refreshEntryHelpers);
                    el.addEventListener('change', refreshEntryHelpers);
                }
            });
        });

        document.querySelectorAll('[data-clear-entry]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearPane(button.getAttribute('data-clear-entry'));
                refreshEntryHelpers();
            });
        });

        document.querySelectorAll('[data-copy-entry]').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.getAttribute('data-copy-entry');
                const source = target === 'seedling' ? 'scion' : 'seedling';
                const targetSupplier = getField(target, 'supplier_id');
                const sourceSupplier = getField(source, 'supplier_id');
                const targetDate = getField(target, 'date_received');
                const sourceDate = getField(source, 'date_received');

                if (targetSupplier && sourceSupplier) {
                    targetSupplier.value = sourceSupplier.value;
                }
                if (targetDate && sourceDate) {
                    targetDate.value = sourceDate.value;
                }

                refreshEntryHelpers();
            });
        });

        // Preserve both entries while the inline supplier form reloads the page.
        // The draft and the tab that asked for the supplier are kept in
        // sessionStorage; on return the supplier is selected in that tab.
        const stockInDraftKey = 'cacaotrack.stockInDraft';
        let supplierTargetPane = null;

        document.querySelectorAll('.js-add-supplier').forEach(function (button) {
            button.addEventListener('click', function () {
                supplierTargetPane = button.getAttribute('data-supplier-target') || null;
            });
        });

        const supplierForm = document.getElementById('supplierForm');
        if (supplierForm) {
            supplierForm.addEventListener('submit', function () {
                try {
                    sessionStorage.setItem(stockInDraftKey, JSON.stringify({
                        target: supplierTargetPane,
                        seedling: snapshotPane('seedling'),
                        scion: snapshotPane('scion'),
                    }));
                } catch (error) {
                    // Browser storage unavailable: the server still validates.
                }
            });
        }

        const newSupplierId = @json(session('new_supplier_id'));
        if (newSupplierId) {
            let draft = null;

            try {
                draft = JSON.parse(sessionStorage.getItem(stockInDraftKey) || 'null');
            } catch (error) {
                draft = null;
            }

            if (draft) {
                restorePane('seedling', draft.seedling || {});
                restorePane('scion', draft.scion || {});

                if (draft.target && fieldIds[draft.target]) {
                    const supplierSelect = getField(draft.target, 'supplier_id');
                    if (supplierSelect) {
                        supplierSelect.value = String(newSupplierId);
                    }
                }
            }

            try {
                sessionStorage.removeItem(stockInDraftKey);
            } catch (error) {
                // Nothing to clean up.
            }
        }

        // Text inputs never submit on Enter; only the footer Save button does.
        if (stockInForm) {
            stockInForm.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && event.target
                    && (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT')) {
                    event.preventDefault();
                }
            });
        }

        // Discard protection: Cancel, X and the backdrop all pass through
        // hide.bs.modal, which is intercepted only while a pane has data.
        let allowStockInClose = false;
        const discardModalEl = document.getElementById('stockInDiscardModal');

        if (stockInModalEl && discardModalEl) {
            stockInModalEl.addEventListener('hide.bs.modal', function (event) {
                if (allowStockInClose || (!hasEntryData('seedling') && !hasEntryData('scion'))) {
                    return;
                }

                event.preventDefault();
                bootstrap.Modal.getOrCreateInstance(discardModalEl).show();
            });

            stockInModalEl.addEventListener('hidden.bs.modal', function () {
                allowStockInClose = false;
            });

            const discardButton = document.getElementById('stockInDiscardBtn');
            if (discardButton) {
                discardButton.addEventListener('click', function () {
                    allowStockInClose = true;
                    bootstrap.Modal.getOrCreateInstance(discardModalEl).hide();
                    bootstrap.Modal.getOrCreateInstance(stockInModalEl).hide();
                    clearPane('seedling');
                    clearPane('scion');
                    refreshEntryHelpers();
                });
            }
        }

        refreshEntryHelpers();

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
                if (formId === 'stockInForm') {
                    allowStockInClose = true;
                }

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + label;
            });
        };

        guardSubmit('stockInForm', 'stockInSubmitBtn', 'Saving…');
        guardSubmit('supplierForm', 'supplierSubmitBtn', 'Saving…');

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
