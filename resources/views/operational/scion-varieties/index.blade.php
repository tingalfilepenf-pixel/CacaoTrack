@extends('layouts.app')

@section('title', 'Scion Variety')
@section('page_title', 'Scion Varieties')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScionVarietyModal">
    <i class="bi bi-plus-lg me-1"></i> Add Scion Variety
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
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('operational.scion-varieties', array_filter(['search' => $filters['search'] ?? null])) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('operational.scion-varieties', array_filter(['search' => $filters['search'] ?? null, 'tab' => 'archived'])) }}">
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
                <div class="text-muted small fw-bold text-uppercase">Total Scion Varieties</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalScionVarieties) }}</div>
                <div class="text-muted small">Scion varieties in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">With Batches</div>
                <div class="fs-3 fw-bold">{{ number_format($scionVarietiesWithBatches) }}</div>
                <div class="text-muted small">Scion varieties assigned to at least one batch</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Batches Assigned</div>
                <div class="fs-3 fw-bold">{{ number_format($totalBatches) }}</div>
                <div class="text-muted small">Batches using these scion varieties</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('operational.scion-varieties') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Scion variety name or description...">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('operational.scion-varieties') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Scion Varieties Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($status === 'archived')
                <i class="bi bi-archive me-2"></i>Archived Scion Varieties
            @else
                Scion Variety Directory
            @endif
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="scionVarietyTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>SCION VARIETY NAME</th>
                        <th>DESCRIPTION</th>
                        <th>BATCHES</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scionVarieties as $scionVariety)
                        @php
                            $details = [
                                'reference' => $scionVariety->reference(),
                                'scion_variety_name' => $scionVariety->scion_variety_name,
                                'description' => $scionVariety->description,
                                'batches' => (int) $scionVariety->batches_count,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $scionVariety->reference() }}</td>
                            <td class="fw-medium">
                                {{ $scionVariety->scion_variety_name }}
                                @if ($status === 'archived' && $scionVariety->archived_at)
                                    <div class="text-muted small fw-normal">Archived {{ $scionVariety->archived_at->format('M j, Y g:i A') }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $scionVariety->description ?: '—' }}</td>
                            <td>
                                @if ($scionVariety->batches_count > 0)
                                    <span class="badge bg-success">{{ number_format($scionVariety->batches_count) }}</span>
                                @else
                                    <span class="badge bg-secondary">0</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#scionVarietyDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editScionVarietyModal"
                                    data-scion-variety-id="{{ $scionVariety->scion_variety_id }}"
                                    data-scion-variety-name="{{ $scionVariety->scion_variety_name }}"
                                    data-description="{{ $scionVariety->description }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('operational.scion-varieties.restore', $scionVariety) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveScionVarietyModal"
                                        data-scion-variety-id="{{ $scionVariety->scion_variety_id }}"
                                        data-scion-variety-name="{{ $scionVariety->scion_variety_name }}" title="Archive">
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
                                    No archived scion varieties. Archived scion varieties appear here and can be restored.
                                @else
                                    No scion varieties found for the current filters.
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
            Showing {{ $scionVarieties->firstItem() ?? 0 }} to {{ $scionVarieties->lastItem() ?? 0 }} of {{ $scionVarieties->total() }} entries
        </span>
        {{ $scionVarieties->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Scion Variety Modal -->
<div class="modal fade" id="addScionVarietyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Scion Variety</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addScionVarietyForm" method="POST" action="{{ route('operational.scion-varieties.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionVarietyName">Scion Variety Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('scion_variety_name', 'scion_variety') is-invalid @enderror" id="addScionVarietyName"
                            name="scion_variety_name" maxlength="255" required value="{{ old('scion_variety_name') }}" placeholder="e.g. ICS-1">
                        @error('scion_variety_name', 'scion_variety')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionVarietyDescription">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control @error('description', 'scion_variety') is-invalid @enderror" id="addScionVarietyDescription"
                            name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        @error('description', 'scion_variety')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addScionVarietySubmitBtn">Save Scion Variety</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Scion Variety Modal -->
<div class="modal fade" id="editScionVarietyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit Scion Variety</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editScionVarietyForm" method="POST" action="{{ route('operational.scion-varieties.update', ['scion_variety' => '__ID__']) }}" data-action-template="{{ route('operational.scion-varieties.update', ['scion_variety' => '__ID__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editScionVarietyName">Scion Variety Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('scion_variety_name', 'scion_variety') is-invalid @enderror" id="editScionVarietyName"
                            name="scion_variety_name" maxlength="255" required value="{{ old('scion_variety_name') }}">
                        @error('scion_variety_name', 'scion_variety')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editScionVarietyDescription">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control @error('description', 'scion_variety') is-invalid @enderror" id="editScionVarietyDescription"
                            name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        @error('description', 'scion_variety')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editScionVarietySubmitBtn">Update Scion Variety</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Scion Variety Modal -->
<div class="modal fade" id="archiveScionVarietyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Archive Scion Variety</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archiveScionVarietyForm" method="POST" action="{{ route('operational.scion-varieties.archive', ['scion_variety' => '__ID__']) }}" data-action-template="{{ route('operational.scion-varieties.archive', ['scion_variety' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Archive <strong id="archiveScionVarietyName">&mdash;</strong>?</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the scion variety from the active directory and from the
                        new-batch selector, but batches and stock-out lines that already use it keep their history. You can restore it
                        anytime from the Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Scion Variety</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scion Variety Details Modal -->
<div class="modal fade" id="scionVarietyDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Scion Variety Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Scion Variety Name:</div>
                    <div class="col-7" id="detailsName">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Description:</div>
                    <div class="col-7" id="detailsDescription">&mdash;</div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Batches Using It:</div>
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
        const addModalEl = document.getElementById('addScionVarietyModal');
        const editModalEl = document.getElementById('editScionVarietyModal');
        const archiveModalEl = document.getElementById('archiveScionVarietyModal');
        const detailsModalEl = document.getElementById('scionVarietyDetailsModal');

        @if (old('edit_scion_variety_id'))
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->scion_variety->any())
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

                const id = button.getAttribute('data-scion-variety-id');
                applyAction(document.getElementById('editScionVarietyForm'), id);
                document.getElementById('editScionVarietyName').value = button.getAttribute('data-scion-variety-name') || '';
                document.getElementById('editScionVarietyDescription').value = button.getAttribute('data-description') || '';
            });
        }

        if (archiveModalEl) {
            archiveModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                applyAction(document.getElementById('archiveScionVarietyForm'), button.getAttribute('data-scion-variety-id'));
                setText('archiveScionVarietyName', button.getAttribute('data-scion-variety-name'));
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
                setText('detailsName', details.scion_variety_name);
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

        guardSubmit('addScionVarietyForm', 'addScionVarietySubmitBtn', 'Saving...');
        guardSubmit('editScionVarietyForm', 'editScionVarietySubmitBtn', 'Saving...');
    });
</script>
@endpush
