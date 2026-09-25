@extends('layouts.app')

@section('title', 'Supplier Management')
@section('page_title', 'Suppliers')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
    <i class="bi bi-plus-lg me-1"></i> Add Supplier
</button>
@endsection

@section('content')
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

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('inventory.suppliers', array_filter(['search' => $filters['search'] ?? null])) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('inventory.suppliers', array_filter(['search' => $filters['search'] ?? null, 'tab' => 'archived'])) }}">
            <i class="bi bi-archive me-1"></i> Archived
            @if ($archivedCount > 0)
                <span class="badge bg-secondary ms-1">{{ $archivedCount }}</span>
            @endif
        </a>
    </li>
</ul>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Suppliers</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalSuppliers) }}</div>
                <div class="text-muted small">Suppliers in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">With Stock-In Activity</div>
                <div class="fs-3 fw-bold">{{ number_format($suppliersWithActivity) }}</div>
                <div class="text-muted small">Suppliers that have received stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-In Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalStockIns) }}</div>
                <div class="text-muted small">Recorded against these suppliers</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.suppliers') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Supplier name, contact, or address...">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.suppliers') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($status === 'archived')
                <i class="bi bi-archive me-2"></i>Archived Suppliers
            @else
                Supplier Directory
            @endif
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="supplierTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>SUPPLIER NAME</th>
                        <th>CONTACT NUMBER</th>
                        <th>ADDRESS</th>
                        <th>STOCK-INS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        @php
                            $details = [
                                'reference' => $supplier->reference(),
                                'supplier_name' => $supplier->supplier_name,
                                'contact_number' => $supplier->contact_number,
                                'address' => $supplier->address,
                                'stock_ins' => (int) $supplier->stock_ins_count,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $supplier->reference() }}</td>
                            <td class="fw-medium">
                                {{ $supplier->supplier_name }}
                                @if ($status === 'archived' && $supplier->archived_at)
                                    <div class="text-muted small fw-normal">Archived {{ $supplier->archived_at->format('M j, Y g:i A') }}</div>
                                @endif
                            </td>
                            <td>{{ $supplier->contact_number }}</td>
                            <td>{{ $supplier->address }}</td>
                            <td>
                                @if ($supplier->stock_ins_count > 0)
                                    <span class="badge bg-success">{{ number_format($supplier->stock_ins_count) }}</span>
                                @else
                                    <span class="badge bg-secondary">0</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#supplierDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editSupplierModal"
                                    data-supplier-id="{{ $supplier->supplier_id }}"
                                    data-supplier-name="{{ $supplier->supplier_name }}"
                                    data-contact="{{ $supplier->contact_number }}"
                                    data-address="{{ $supplier->address }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('inventory.suppliers.restore', $supplier) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveSupplierModal"
                                        data-supplier-id="{{ $supplier->supplier_id }}"
                                        data-supplier-name="{{ $supplier->supplier_name }}" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                @if ($status === 'archived')
                                    No archived suppliers. Archived suppliers appear here and can be restored.
                                @else
                                    No suppliers found for the current filters.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $suppliers->firstItem() ?? 0 }} to {{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }} entries
        </span>
        {{ $suppliers->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSupplierForm" method="POST" action="{{ route('inventory.suppliers.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addSupplierName">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('supplier_name', 'supplier') is-invalid @enderror" id="addSupplierName"
                            name="supplier_name" maxlength="255" required value="{{ old('supplier_name') }}">
                        @error('supplier_name', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addSupplierContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'supplier') is-invalid @enderror" id="addSupplierContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addSupplierAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'supplier') is-invalid @enderror" id="addSupplierAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSupplierSubmitBtn">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSupplierForm" method="POST" action="{{ route('inventory.suppliers.update', ['supplier' => '__ID__']) }}" data-action-template="{{ route('inventory.suppliers.update', ['supplier' => '__ID__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editSupplierName">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('supplier_name', 'supplier') is-invalid @enderror" id="editSupplierName"
                            name="supplier_name" maxlength="255" required value="{{ old('supplier_name') }}">
                        @error('supplier_name', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editSupplierContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'supplier') is-invalid @enderror" id="editSupplierContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editSupplierAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'supplier') is-invalid @enderror" id="editSupplierAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'supplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSupplierSubmitBtn">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Supplier Modal -->
<div class="modal fade" id="archiveSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Archive Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archiveSupplierForm" method="POST" action="{{ route('inventory.suppliers.archive', ['supplier' => '__ID__']) }}" data-action-template="{{ route('inventory.suppliers.archive', ['supplier' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Archive <strong id="archiveSupplierName">&mdash;</strong>?</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the supplier from the active directory and from the
                        New Stock In form, but all existing stock-in records keep their history. You can restore it anytime from the
                        Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Supplier Details Modal -->
<div class="modal fade" id="supplierDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Supplier Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Supplier Name:</div>
                    <div class="col-7" id="detailsName">&mdash;</div>
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
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Stock-In Records:</div>
                    <div class="col-7" id="detailsStockIns">&mdash;</div>
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
        const addModalEl = document.getElementById('addSupplierModal');
        const editModalEl = document.getElementById('editSupplierModal');
        const archiveModalEl = document.getElementById('archiveSupplierModal');
        const detailsModalEl = document.getElementById('supplierDetailsModal');

        @if (old('edit_supplier_id'))
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->supplier->any())
            new bootstrap.Modal(addModalEl).show();
        @endif

        const setText = function (id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value == null || value === '' ? '—' : value;
            }
        };

        const applyAction = function (form, id) {
            const template = form.getAttribute('data-action-template') || form.getAttribute('action');
            form.setAttribute('action', template.replace('__ID__', id));
        };

        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                const id = button.getAttribute('data-supplier-id');
                applyAction(document.getElementById('editSupplierForm'), id);
                document.getElementById('editSupplierName').value = button.getAttribute('data-supplier-name') || '';
                document.getElementById('editSupplierContact').value = button.getAttribute('data-contact') || '';
                document.getElementById('editSupplierAddress').value = button.getAttribute('data-address') || '';
            });
        }

        if (archiveModalEl) {
            archiveModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                applyAction(document.getElementById('archiveSupplierForm'), button.getAttribute('data-supplier-id'));
                setText('archiveSupplierName', button.getAttribute('data-supplier-name'));
            });
        }

        if (detailsModalEl) {
            detailsModalEl.addEventListener('show.bs.modal', function (event) {
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

                setText('detailsReference', details.reference);
                setText('detailsName', details.supplier_name);
                setText('detailsContact', details.contact_number);
                setText('detailsAddress', details.address);
                setText('detailsStockIns', Number(details.stock_ins || 0).toLocaleString());
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

        guardSubmit('addSupplierForm', 'addSupplierSubmitBtn', 'Saving...');
        guardSubmit('editSupplierForm', 'editSupplierSubmitBtn', 'Saving...');
    });
</script>
@endpush
