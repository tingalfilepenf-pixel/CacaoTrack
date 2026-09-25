@extends('layouts.app')

@section('title', 'Batch Management')
@section('page_title', 'Batches')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
    <i class="bi bi-plus-lg me-1"></i> Add Batch
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
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('inventory.batches', collect($filters)->except('tab')->filter()->all()) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('inventory.batches', array_merge(collect($filters)->except('tab')->filter()->all(), ['tab' => 'archived'])) }}">
            <i class="bi bi-archive me-1"></i> Archived
            @if ($archivedCount > 0)
                <span class="badge bg-secondary ms-1">{{ $archivedCount }}</span>
            @endif
        </a>
    </li>
</ul>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Batches</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalBatches) }}</div>
                <div class="text-muted small">Batches in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Active</div>
                <div class="fs-3 fw-bold">{{ number_format($activeBatches) }}</div>
                <div class="text-muted small">Batches with an active status</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Inactive</div>
                <div class="fs-3 fw-bold">{{ number_format($inactiveBatches) }}</div>
                <div class="text-muted small">Batches with an inactive status</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">With Mortality</div>
                <div class="fs-3 fw-bold">{{ number_format($batchesWithMortality) }}</div>
                <div class="text-muted small">Batches with mortality records</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.batches') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Batch code...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterVariety" class="form-label text-muted small fw-bold mb-1">Variety</label>
                <select class="form-select form-select-sm" id="filterVariety" name="variety_id">
                    <option value="">All varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected((string) ($filters['variety_id'] ?? '') === (string) $variety->variety_id)>{{ $variety->variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterScion" class="form-label text-muted small fw-bold mb-1">Scion</label>
                <select class="form-select form-select-sm" id="filterScion" name="scion_id">
                    <option value="">All scions</option>
                    @foreach ($scions as $scion)
                        <option value="{{ $scion->scion_id }}" @selected((string) ($filters['scion_id'] ?? '') === (string) $scion->scion_id)>{{ $scion->scion_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Batch::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.batches') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Batches Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($status === 'archived')
                <i class="bi bi-archive me-2"></i>Archived Batches
            @else
                Batch Directory
            @endif
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="batchTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH CODE</th>
                        <th>VARIETY</th>
                        <th>SCION</th>
                        <th>POT TYPE</th>
                        <th>GRAFTED</th>
                        <th class="text-end">TOTAL</th>
                        <th class="text-end">VIABLE</th>
                        <th class="text-end">AVAILABLE</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        @php
                            $used = $batch->hasOperationalRecords();
                            $details = [
                                'batch_code' => $batch->batch_code,
                                'variety' => $batch->variety?->variety_name,
                                'scion' => $batch->scion?->scion_name,
                                'pot_type' => $batch->potType?->pot_name,
                                'grafted_date' => $batch->grafted_date?->format('M d, Y'),
                                'created_at' => $batch->created_at?->format('M d, Y H:i'),
                                'total_grafted' => (int) $batch->total_grafted,
                                'viable_count' => (int) $batch->viable_count,
                                'available_count' => (int) $batch->available_count,
                                'inventory_quantity' => $batch->inventory ? (int) $batch->inventory->current_quantity : null,
                                'mortality' => (int) $batch->mortality_records_count,
                                'replanting' => (int) $batch->replanting_records_count,
                                'status' => $batch->status,
                                'used' => $used,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">
                                {{ $batch->batch_code }}
                                @if ($status === 'archived' && $batch->archived_at)
                                    <div class="text-muted small fw-normal">Archived {{ $batch->archived_at->format('M j, Y g:i A') }}</div>
                                @endif
                            </td>
                            <td>{{ $batch->variety?->variety_name ?? '—' }}</td>
                            <td>{{ $batch->scion?->scion_name ?? '—' }}</td>
                            <td class="text-muted">{{ $batch->potType?->pot_name ?? '—' }}</td>
                            <td class="text-muted">{{ $batch->grafted_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end">{{ number_format($batch->total_grafted) }}</td>
                            <td class="text-end">{{ number_format($batch->viable_count) }}</td>
                            <td class="text-end">{{ number_format($batch->available_count) }}</td>
                            <td>
                                @if ($batch->status === \App\Models\Batch::STATUS_ACTIVE)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#batchDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editBatchModal"
                                    data-batch-id="{{ $batch->batch_id }}"
                                    data-batch-code="{{ $batch->batch_code }}"
                                    data-variety-id="{{ $batch->variety_id }}"
                                    data-scion-id="{{ $batch->scion_id }}"
                                    data-pot-id="{{ $batch->pot_id }}"
                                    data-total-grafted="{{ $batch->total_grafted }}"
                                    data-viable-count="{{ $batch->viable_count }}"
                                    data-grafted-date="{{ $batch->grafted_date?->format('Y-m-d') }}"
                                    data-status="{{ $batch->status }}"
                                    data-used="{{ $used ? '1' : '0' }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('inventory.batches.restore', $batch) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveBatchModal"
                                        data-batch-id="{{ $batch->batch_id }}"
                                        data-batch-code="{{ $batch->batch_code }}" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                @if ($status === 'archived')
                                    No archived batches. Archived batches appear here and can be restored.
                                @else
                                    No batches found for the current filters.
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
            Showing {{ $batches->firstItem() ?? 0 }} to {{ $batches->lastItem() ?? 0 }} of {{ $batches->total() }} entries
        </span>
        {{ $batches->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addBatchForm" method="POST" action="{{ route('inventory.batches.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addBatchCode">Batch Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('batch_code', 'batch') is-invalid @enderror" id="addBatchCode"
                                name="batch_code" maxlength="255" required value="{{ old('batch_code') }}" placeholder="e.g. B004">
                            @error('batch_code', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addVariety">Variety <span class="text-danger">*</span></label>
                            <select class="form-select @error('variety_id', 'batch') is-invalid @enderror" id="addVariety" name="variety_id" required>
                                <option value="">Select a variety</option>
                                @foreach ($varieties as $variety)
                                    <option value="{{ $variety->variety_id }}" @selected((string) old('variety_id') === (string) $variety->variety_id) @disabled($variety->isArchived())>{{ $variety->variety_name }}@if ($variety->isArchived()) (archived)@endif</option>
                                @endforeach
                            </select>
                            @error('variety_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addScion">Scion <span class="text-danger">*</span></label>
                            <select class="form-select @error('scion_id', 'batch') is-invalid @enderror" id="addScion" name="scion_id" required>
                                <option value="">Select a scion</option>
                                @foreach ($scions as $scion)
                                    <option value="{{ $scion->scion_id }}" @selected((string) old('scion_id') === (string) $scion->scion_id) @disabled($scion->isArchived())>{{ $scion->scion_name }}@if ($scion->isArchived()) (archived)@endif</option>
                                @endforeach
                            </select>
                            @error('scion_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addPotType">Pot Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('pot_id', 'batch') is-invalid @enderror" id="addPotType" name="pot_id" required>
                                <option value="">Select a pot type</option>
                                @foreach ($potTypes as $potType)
                                    <option value="{{ $potType->pot_id }}" @selected((string) old('pot_id') === (string) $potType->pot_id) @disabled($potType->isArchived())>{{ $potType->pot_name }}@if ($potType->isArchived()) (archived)@endif</option>
                                @endforeach
                            </select>
                            @error('pot_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addGraftedDate">Grafted Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('grafted_date', 'batch') is-invalid @enderror" id="addGraftedDate"
                                name="grafted_date" max="{{ now()->format('Y-m-d') }}" required value="{{ old('grafted_date') }}">
                            @error('grafted_date', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addStatus">Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('status', 'batch') is-invalid @enderror" id="addStatus" name="status" required>
                                @foreach (\App\Models\Batch::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(old('status', \App\Models\Batch::STATUS_ACTIVE) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            @error('status', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addTotalGrafted">Total Grafted <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('total_grafted', 'batch') is-invalid @enderror" id="addTotalGrafted"
                                name="total_grafted" min="1" step="1" required value="{{ old('total_grafted') }}">
                            @error('total_grafted', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="addViableCount">Viable Count <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('viable_count', 'batch') is-invalid @enderror" id="addViableCount"
                                name="viable_count" min="0" step="1" required value="{{ old('viable_count') }}">
                            @error('viable_count', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Cannot exceed total grafted. Available count is set to this value.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addBatchSubmitBtn">Save Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBatchForm" method="POST" action="{{ route('inventory.batches.update', ['batch' => '__ID__']) }}" data-action-template="{{ route('inventory.batches.update', ['batch' => '__ID__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="alert alert-warning small" id="editCountsLockedNotice" style="display: none;">
                        <i class="bi bi-lock me-1"></i>
                        This batch already has operational records, so total grafted and viable count are locked.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editBatchCode">Batch Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('batch_code', 'batch') is-invalid @enderror" id="editBatchCode"
                                name="batch_code" maxlength="255" required value="{{ old('batch_code') }}">
                            @error('batch_code', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editVariety">Variety <span class="text-danger">*</span></label>
                            <select class="form-select @error('variety_id', 'batch') is-invalid @enderror" id="editVariety" name="variety_id" required>
                                @foreach ($varieties as $variety)
                                    <option value="{{ $variety->variety_id }}" @selected((string) old('variety_id') === (string) $variety->variety_id)>{{ $variety->variety_name }}</option>
                                @endforeach
                            </select>
                            @error('variety_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editScion">Scion <span class="text-danger">*</span></label>
                            <select class="form-select @error('scion_id', 'batch') is-invalid @enderror" id="editScion" name="scion_id" required>
                                @foreach ($scions as $scion)
                                    <option value="{{ $scion->scion_id }}" @selected((string) old('scion_id') === (string) $scion->scion_id)>{{ $scion->scion_name }}</option>
                                @endforeach
                            </select>
                            @error('scion_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editPotType">Pot Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('pot_id', 'batch') is-invalid @enderror" id="editPotType" name="pot_id" required>
                                @foreach ($potTypes as $potType)
                                    <option value="{{ $potType->pot_id }}" @selected((string) old('pot_id') === (string) $potType->pot_id)>{{ $potType->pot_name }}</option>
                                @endforeach
                            </select>
                            @error('pot_id', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editGraftedDate">Grafted Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('grafted_date', 'batch') is-invalid @enderror" id="editGraftedDate"
                                name="grafted_date" max="{{ now()->format('Y-m-d') }}" required value="{{ old('grafted_date') }}">
                            @error('grafted_date', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editStatus">Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('status', 'batch') is-invalid @enderror" id="editStatus" name="status" required>
                                @foreach (\App\Models\Batch::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            @error('status', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editTotalGrafted">Total Grafted</label>
                            <input type="number" class="form-control @error('total_grafted', 'batch') is-invalid @enderror" id="editTotalGrafted"
                                name="total_grafted" min="1" step="1" value="{{ old('total_grafted') }}">
                            @error('total_grafted', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="editViableCount">Viable Count</label>
                            <input type="number" class="form-control @error('viable_count', 'batch') is-invalid @enderror" id="editViableCount"
                                name="viable_count" min="0" step="1" value="{{ old('viable_count') }}">
                            @error('viable_count', 'batch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Available count and inventory follow this value while the batch is unused.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editBatchSubmitBtn">Update Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Batch Modal -->
<div class="modal fade" id="archiveBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Archive Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archiveBatchForm" method="POST" action="{{ route('inventory.batches.archive', ['batch' => '__ID__']) }}" data-action-template="{{ route('inventory.batches.archive', ['batch' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Archive <strong id="archiveBatchCode">&mdash;</strong>?</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the batch from the active directory and from new
                        operational forms, but its inventory, mortality, replanting, stock and activity history are preserved.
                        You can restore it anytime from the Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Batch Details: <span id="detailsBatchCode">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Variety:</div>
                    <div class="col-7" id="detailsVariety">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Scion:</div>
                    <div class="col-7" id="detailsScion">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Pot Type:</div>
                    <div class="col-7" id="detailsPotType">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Grafted Date:</div>
                    <div class="col-7" id="detailsGraftedDate">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Created At:</div>
                    <div class="col-7" id="detailsCreatedAt">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Status:</div>
                    <div class="col-7" id="detailsStatus">&mdash;</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Total Grafted:</div>
                    <div class="col-7" id="detailsTotal">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Viable Count:</div>
                    <div class="col-7" id="detailsViable">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Available Count:</div>
                    <div class="col-7" id="detailsAvailable">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Current Inventory:</div>
                    <div class="col-7" id="detailsInventory">&mdash;</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Mortality Records:</div>
                    <div class="col-7" id="detailsMortality">&mdash;</div>
                </div>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Replanting Records:</div>
                    <div class="col-7" id="detailsReplanting">&mdash;</div>
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
        const addModalEl = document.getElementById('addBatchModal');
        const editModalEl = document.getElementById('editBatchModal');
        const archiveModalEl = document.getElementById('archiveBatchModal');
        const detailsModalEl = document.getElementById('batchDetailsModal');

        @if (old('edit_batch_id'))
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->batch->any())
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

                const used = button.getAttribute('data-used') === '1';
                applyAction(document.getElementById('editBatchForm'), button.getAttribute('data-batch-id'));
                document.getElementById('editBatchCode').value = button.getAttribute('data-batch-code') || '';
                document.getElementById('editVariety').value = button.getAttribute('data-variety-id') || '';
                document.getElementById('editScion').value = button.getAttribute('data-scion-id') || '';
                document.getElementById('editPotType').value = button.getAttribute('data-pot-id') || '';
                document.getElementById('editGraftedDate').value = button.getAttribute('data-grafted-date') || '';
                document.getElementById('editStatus').value = button.getAttribute('data-status') || 'active';

                const totalInput = document.getElementById('editTotalGrafted');
                const viableInput = document.getElementById('editViableCount');
                totalInput.value = button.getAttribute('data-total-grafted') || '';
                viableInput.value = button.getAttribute('data-viable-count') || '';
                totalInput.disabled = used;
                viableInput.disabled = used;
                document.getElementById('editCountsLockedNotice').style.display = used ? 'block' : 'none';
            });
        }

        if (archiveModalEl) {
            archiveModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                applyAction(document.getElementById('archiveBatchForm'), button.getAttribute('data-batch-id'));
                setText('archiveBatchCode', button.getAttribute('data-batch-code'));
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

                setText('detailsBatchCode', details.batch_code);
                setText('detailsVariety', details.variety);
                setText('detailsScion', details.scion);
                setText('detailsPotType', details.pot_type);
                setText('detailsGraftedDate', details.grafted_date);
                setText('detailsCreatedAt', details.created_at);
                setText('detailsStatus', details.status ? details.status.charAt(0).toUpperCase() + details.status.slice(1) : '');
                setText('detailsTotal', Number(details.total_grafted || 0).toLocaleString());
                setText('detailsViable', Number(details.viable_count || 0).toLocaleString());
                setText('detailsAvailable', Number(details.available_count || 0).toLocaleString());
                setText('detailsInventory', details.inventory_quantity == null ? '—' : Number(details.inventory_quantity).toLocaleString());
                setText('detailsMortality', Number(details.mortality || 0).toLocaleString());
                setText('detailsReplanting', Number(details.replanting || 0).toLocaleString());
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

        guardSubmit('addBatchForm', 'addBatchSubmitBtn', 'Saving...');
        guardSubmit('editBatchForm', 'editBatchSubmitBtn', 'Saving...');
    });
</script>
@endpush
