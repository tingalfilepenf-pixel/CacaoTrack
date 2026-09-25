@extends('layouts.app')

@section('title', 'Batches')
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
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('operational.batches', collect($filters)->except('tab')->filter()->all()) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('operational.batches', array_merge(collect($filters)->except('tab')->filter()->all(), ['tab' => 'archived'])) }}">
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
        <form method="GET" action="{{ route('operational.batches') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Batch code...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterScionVariety" class="form-label text-muted small fw-bold mb-1">Scion Variety</label>
                <select class="form-select form-select-sm" id="filterScionVariety" name="scion_variety_id">
                    <option value="">All scion varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected((string) ($filters['scion_variety_id'] ?? '') === (string) $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Batch::STATUSES as $batchStatus)
                        <option value="{{ $batchStatus }}" @selected(($filters['status'] ?? '') === $batchStatus)>{{ ucfirst($batchStatus) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('operational.batches') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
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
                        <th>SCION VARIETY</th>
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
                            $latestInventory = $batch->latestInventory;
                            $details = [
                                'batch_code' => $batch->batch_code,
                                'scion_variety' => $batch->scionVariety?->scion_variety_name,
                                'grafted_date' => $batch->grafted_date?->format('M d, Y'),
                                'created_by' => $batch->user?->name,
                                'created_at' => $batch->created_at?->format('M d, Y H:i'),
                                'total_grafted' => (int) $batch->total_grafted,
                                'viable_count' => (int) $batch->viable_count,
                                'available_count' => (int) $batch->available_count,
                                'status' => $batch->status,
                                'mortality' => (int) $batch->mortality_records_count,
                                'replanting' => (int) $batch->replanting_records_count,
                                'inventory_date' => $latestInventory?->inventory_date?->format('M d, Y'),
                                'inventory_status' => $latestInventory?->status,
                                'stock_outs' => $batch->stockOutDetails()->count(),
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
                            <td>{{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}</td>
                            <td class="text-muted">{{ $batch->grafted_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end">{{ number_format($batch->total_grafted) }}</td>
                            <td class="text-end">{{ number_format($batch->viable_count) }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($batch->available_count) }}</td>
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
                                    data-scion-variety-id="{{ $batch->scion_variety_id }}"
                                    data-total-grafted="{{ $batch->total_grafted }}"
                                    data-viable-count="{{ $batch->viable_count }}"
                                    data-grafted-date="{{ $batch->grafted_date?->format('Y-m-d') }}"
                                    data-status="{{ $batch->status }}"
                                    data-used="{{ $used ? '1' : '0' }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('operational.batches.restore', $batch) }}" class="d-inline">
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
                            <td colspan="8" class="text-center text-muted py-5">
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
            <form id="addBatchForm" method="POST" action="{{ route('operational.batches.manage.store') }}">
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
                            <label class="form-label text-muted small fw-bold" for="addScionVariety">Scion Variety <span class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select @error('scion_variety_id', 'batch') is-invalid @enderror" id="addScionVariety" name="scion_variety_id">
                                <option value="">Not yet assigned</option>
                                @foreach ($scionVarieties as $scionVariety)
                                    <option value="{{ $scionVariety->scion_variety_id }}" @selected((string) old('scion_variety_id') === (string) $scionVariety->scion_variety_id) @disabled($scionVariety->isArchived())>{{ $scionVariety->scion_variety_name }}@if ($scionVariety->isArchived()) (archived)@endif</option>
                                @endforeach
                            </select>
                            @error('scion_variety_id', 'batch')
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
                                @foreach (\App\Models\Batch::STATUSES as $batchStatus)
                                    <option value="{{ $batchStatus }}" @selected(old('status', \App\Models\Batch::STATUS_ACTIVE) === $batchStatus)>{{ ucfirst($batchStatus) }}</option>
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
            <form id="editBatchForm" method="POST" action="{{ route('operational.batches.update', ['batch' => '__ID__']) }}" data-action-template="{{ route('operational.batches.update', ['batch' => '__ID__']) }}">
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
                            <label class="form-label text-muted small fw-bold" for="editScionVariety">Scion Variety <span class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select @error('scion_variety_id', 'batch') is-invalid @enderror" id="editScionVariety" name="scion_variety_id">
                                <option value="">Not yet assigned</option>
                                @foreach ($scionVarieties as $scionVariety)
                                    <option value="{{ $scionVariety->scion_variety_id }}" @selected((string) old('scion_variety_id') === (string) $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}@if ($scionVariety->isArchived()) (archived)@endif</option>
                                @endforeach
                            </select>
                            @error('scion_variety_id', 'batch')
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
                                @foreach (\App\Models\Batch::STATUSES as $batchStatus)
                                    <option value="{{ $batchStatus }}" @selected(old('status') === $batchStatus)>{{ ucfirst($batchStatus) }}</option>
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
            <form id="archiveBatchForm" method="POST" action="{{ route('operational.batches.archive', ['batch' => '__ID__']) }}" data-action-template="{{ route('operational.batches.archive', ['batch' => '__ID__']) }}">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Batch Details: <span id="detailsBatchCode">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="text-muted small text-uppercase mb-3">Summary</h6>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Total Grafted:</div>
                                    <div class="col-6" id="detailsTotal">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Viable:</div>
                                    <div class="col-6" id="detailsViable">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Available:</div>
                                    <div class="col-6 text-success fw-bold" id="detailsAvailable">&mdash;</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-muted small fw-bold">Status:</div>
                                    <div class="col-6" id="detailsStatus">&mdash;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="text-muted small text-uppercase mb-3">Batch Information</h6>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Scion Variety:</div>
                                    <div class="col-6" id="detailsScionVariety">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Grafted Date:</div>
                                    <div class="col-6" id="detailsGraftedDate">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-muted small fw-bold">Created By:</div>
                                    <div class="col-6" id="detailsCreatedBy">&mdash;</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-muted small fw-bold">Created At:</div>
                                    <div class="col-6" id="detailsCreatedAt">&mdash;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="text-muted small text-uppercase mb-3">Related Operations</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small fw-bold">Mortality</div>
                            <div class="fs-5 fw-bold text-danger" id="detailsMortality">&mdash;</div>
                            <div class="text-muted small">recorded entries</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small fw-bold">Replanting</div>
                            <div class="fs-5 fw-bold text-warning" id="detailsReplanting">&mdash;</div>
                            <div class="text-muted small">recorded entries</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small fw-bold">Inventory</div>
                            <div class="fs-5 fw-bold" id="detailsInventoryDate">&mdash;</div>
                            <div class="text-muted small">latest snapshot (<span id="detailsInventoryStatus">&mdash;</span>)</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small fw-bold">Stock Out</div>
                            <div class="fs-5 fw-bold text-secondary" id="detailsStockOuts">&mdash;</div>
                            <div class="text-muted small">detail lines</div>
                        </div>
                    </div>
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
                document.getElementById('editScionVariety').value = button.getAttribute('data-scion-variety-id') || '';
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
                setText('detailsScionVariety', details.scion_variety);
                setText('detailsGraftedDate', details.grafted_date);
                setText('detailsCreatedBy', details.created_by);
                setText('detailsCreatedAt', details.created_at);
                setText('detailsStatus', details.status ? details.status.charAt(0).toUpperCase() + details.status.slice(1) : '');
                setText('detailsTotal', Number(details.total_grafted || 0).toLocaleString());
                setText('detailsViable', Number(details.viable_count || 0).toLocaleString());
                setText('detailsAvailable', Number(details.available_count || 0).toLocaleString());
                setText('detailsMortality', Number(details.mortality || 0).toLocaleString());
                setText('detailsReplanting', Number(details.replanting || 0).toLocaleString());
                setText('detailsInventoryDate', details.inventory_date);
                setText('detailsInventoryStatus', details.inventory_status ? details.inventory_status.charAt(0).toUpperCase() + details.inventory_status.slice(1) : '—');
                setText('detailsStockOuts', Number(details.stock_outs || 0).toLocaleString());
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
