@extends('layouts.app')

@section('title', 'Pot Type Management')
@section('page_title', 'Pot Types')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPotTypeModal">
    <i class="bi bi-plus-lg me-1"></i> Add Pot Type
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
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('inventory.pot-types', array_filter(['search' => $filters['search'] ?? null])) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('inventory.pot-types', array_filter(['search' => $filters['search'] ?? null, 'tab' => 'archived'])) }}">
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
                <div class="text-muted small fw-bold text-uppercase">Total Pot Types</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalPotTypes) }}</div>
                <div class="text-muted small">Pot types in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">With Batches</div>
                <div class="fs-3 fw-bold">{{ number_format($potTypesWithBatches) }}</div>
                <div class="text-muted small">Pot types used by at least one batch</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Batches</div>
                <div class="fs-3 fw-bold">{{ number_format($totalBatches) }}</div>
                <div class="text-muted small">Batches classified under these pot types</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.pot-types') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Pot type name or description...">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.pot-types') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Pot Types Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($status === 'archived')
                <i class="bi bi-archive me-2"></i>Archived Pot Types
            @else
                Pot Type Directory
            @endif
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="potTypeTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>POT TYPE</th>
                        <th>DESCRIPTION</th>
                        <th>BATCHES</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($potTypes as $potType)
                        @php
                            $details = [
                                'reference' => $potType->reference(),
                                'pot_name' => $potType->pot_name,
                                'description' => $potType->description,
                                'batches' => (int) $potType->batches_count,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $potType->reference() }}</td>
                            <td class="fw-medium">
                                {{ $potType->pot_name }}
                                @if ($status === 'archived' && $potType->archived_at)
                                    <div class="text-muted small fw-normal">Archived {{ $potType->archived_at->format('M j, Y g:i A') }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $potType->description ?: '—' }}</td>
                            <td>
                                @if ($potType->batches_count > 0)
                                    <span class="badge bg-success">{{ number_format($potType->batches_count) }}</span>
                                @else
                                    <span class="badge bg-secondary">0</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#potTypeDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editPotTypeModal"
                                    data-pot-id="{{ $potType->pot_id }}"
                                    data-pot-name="{{ $potType->pot_name }}"
                                    data-description="{{ $potType->description }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('inventory.pot-types.restore', $potType) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archivePotTypeModal"
                                        data-pot-id="{{ $potType->pot_id }}"
                                        data-pot-name="{{ $potType->pot_name }}" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                @if ($status === 'archived')
                                    No archived pot types. Archived pot types appear here and can be restored.
                                @else
                                    No pot types found for the current filters.
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
            Showing {{ $potTypes->firstItem() ?? 0 }} to {{ $potTypes->lastItem() ?? 0 }} of {{ $potTypes->total() }} entries
        </span>
        {{ $potTypes->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Pot Type Modal -->
<div class="modal fade" id="addPotTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Pot Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addPotTypeForm" method="POST" action="{{ route('inventory.pot-types.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addPotName">Pot Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('pot_name', 'pot') is-invalid @enderror" id="addPotName"
                            name="pot_name" maxlength="255" required value="{{ old('pot_name') }}">
                        @error('pot_name', 'pot')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addPotDescription">Description</label>
                        <textarea class="form-control @error('description', 'pot') is-invalid @enderror" id="addPotDescription"
                            name="description" rows="3" maxlength="1000" placeholder="Dimensions, material, etc...">{{ old('description') }}</textarea>
                        @error('description', 'pot')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addPotTypeSubmitBtn">Save Pot Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Pot Type Modal -->
<div class="modal fade" id="editPotTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit Pot Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPotTypeForm" method="POST" action="{{ route('inventory.pot-types.update', ['pot_type' => '__ID__']) }}" data-action-template="{{ route('inventory.pot-types.update', ['pot_type' => '__ID__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editPotName">Pot Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('pot_name', 'pot') is-invalid @enderror" id="editPotName"
                            name="pot_name" maxlength="255" required value="{{ old('pot_name') }}">
                        @error('pot_name', 'pot')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editPotDescription">Description</label>
                        <textarea class="form-control @error('description', 'pot') is-invalid @enderror" id="editPotDescription"
                            name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
                        @error('description', 'pot')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editPotTypeSubmitBtn">Update Pot Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Pot Type Modal -->
<div class="modal fade" id="archivePotTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Archive Pot Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archivePotTypeForm" method="POST" action="{{ route('inventory.pot-types.archive', ['pot_type' => '__ID__']) }}" data-action-template="{{ route('inventory.pot-types.archive', ['pot_type' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Archive <strong id="archivePotName">&mdash;</strong>?</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the pot type from the active directory and from new
                        batch forms, but existing batches keep their history. You can restore it anytime from the Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Pot Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pot Type Details Modal -->
<div class="modal fade" id="potTypeDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Pot Type Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Pot Type Name:</div>
                    <div class="col-7" id="detailsName">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Description:</div>
                    <div class="col-7" id="detailsDescription">&mdash;</div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Batch Records:</div>
                    <div class="col-7" id="detailsBatches">&mdash;</div>
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
        const addModalEl = document.getElementById('addPotTypeModal');
        const editModalEl = document.getElementById('editPotTypeModal');
        const archiveModalEl = document.getElementById('archivePotTypeModal');
        const detailsModalEl = document.getElementById('potTypeDetailsModal');

        @if (old('edit_pot_id'))
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->pot->any())
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

                const id = button.getAttribute('data-pot-id');
                applyAction(document.getElementById('editPotTypeForm'), id);
                document.getElementById('editPotName').value = button.getAttribute('data-pot-name') || '';
                document.getElementById('editPotDescription').value = button.getAttribute('data-description') || '';
            });
        }

        if (archiveModalEl) {
            archiveModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                applyAction(document.getElementById('archivePotTypeForm'), button.getAttribute('data-pot-id'));
                setText('archivePotName', button.getAttribute('data-pot-name'));
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
                setText('detailsName', details.pot_name);
                setText('detailsDescription', details.description);
                setText('detailsBatches', Number(details.batches || 0).toLocaleString());
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

        guardSubmit('addPotTypeForm', 'addPotTypeSubmitBtn', 'Saving...');
        guardSubmit('editPotTypeForm', 'editPotTypeSubmitBtn', 'Saving...');
    });
</script>
@endpush
