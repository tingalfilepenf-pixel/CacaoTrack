@extends('layouts.app')

@section('title', 'Inventory')
@section('page_title', 'Inventory')

@section('page_actions')
@if (auth()->user()->hasPermission('record_operations'))
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
        <i class="bi bi-plus-lg me-1"></i> Add Batch
    </button>
@endif
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
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

<!-- Summary cards (filter-aware; every figure aggregates the same live per-batch values as the table) -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-2 row-cols-xl-4 g-3 mb-4">
    <div class="col">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Mortality</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($totalMortality) }}</div>
                <div class="text-muted small">Total plants recorded as dead</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Regrafted</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalRegrafted) }}</div>
                <div class="text-muted small">Total plants regrafted</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Active Inventory</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format($totalActiveInventory) }}</div>
                <div class="text-muted small">Plants currently available</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-dark">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Batches</div>
                <div class="fs-3 fw-bold text-dark">{{ number_format($totalBatches) }}</div>
                <div class="text-muted small">Total number of batches</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.index') }}" class="row g-3 align-items-end" id="inventoryFilterForm" data-auto-filter>
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search Batch</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Enter batch code...">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterScionVariety" class="form-label text-muted small fw-bold mb-1">Scion Variety</label>
                <select class="form-select form-select-sm" id="filterScionVariety" name="scion_variety_id">
                    <option value="">All Scion Varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(($filters['scion_variety_id'] ?? null) == $scionVariety->scion_variety_id)>
                            {{ $scionVariety->scion_variety_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" name="status" title="Derived from the 21-day cycle and the live available balance">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\Batch::lifecycleLabels() as $lifecycleValue => $lifecycleLabel)
                        <option value="{{ $lifecycleValue }}" @selected(($filters['status'] ?? null) === $lifecycleValue)>{{ $lifecycleLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterMonth" class="form-label text-muted small fw-bold mb-1">Batch Month</label>
                <select class="form-select form-select-sm" id="filterMonth" name="month">
                    <option value="">All Months</option>
                    @foreach ($months as $number => $name)
                        <option value="{{ $number }}" @selected((string) ($filters['month'] ?? '') === (string) $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label for="filterYear" class="form-label text-muted small fw-bold mb-1">Batch Year</label>
                <input type="number" class="form-control form-control-sm" id="filterYear" name="year" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary w-100" title="Clear all filters">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Clear Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Batch Inventory Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Batch Inventory</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH DETAILS</th>
                        <th>SCION VARIETY</th>
                        <th class="text-end">TOTAL GRAFTED</th>
                        <th class="text-end">REGRAFT</th>
                        <th class="text-end">MORTALITY</th>
                        <th class="text-end">AVAILABLE</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        @php
                            $summary = $summaries[$batch->batch_id] ?? null;
                            $meta = $nodeMeta[$batch->batch_id] ?? null;
                            $depth = $meta['depth'] ?? 0;
                            $parentCode = $meta['parent_code'] ?? null;
                            $parentMissing = $meta['parent_missing'] ?? false;
                            $hasChildren = $meta['has_children'] ?? false;
                            // Same collapsible sub-batch behaviour as the Batches page:
                            // every nested row carries a class for each ancestor so a
                            // parent's chevron toggles its whole subtree.
                            $collapseClasses = collect($meta['ancestors'] ?? [])
                                ->map(fn ($ancestorId) => 'child-of-'.$ancestorId)
                                ->implode(' ');
                            $rowClass = $depth > 0 ? trim('collapse show '.$collapseClasses) : '';
                            $lifecycle = $summary['lifecycle_status'] ?? \App\Models\Batch::LIFECYCLE_GROWING;
                            $lifecycleClass = match ($lifecycle) {
                                \App\Models\Batch::LIFECYCLE_READY => 'bg-success',
                                \App\Models\Batch::LIFECYCLE_COMPLETED => 'badge-lifecycle-completed',
                                \App\Models\Batch::LIFECYCLE_CANCELLED => 'bg-danger',
                                default => 'bg-warning text-dark',
                            };
                            // The read-only View modal payload (no extra queries).
                            $rowPayload = [
                                'batch_code' => $batch->batch_code,
                                'parent_code' => $parentCode,
                                'scion_variety' => $batch->scionVariety?->scion_variety_name ?? 'Not assigned',
                                'grafted_date' => $formatDate($batch->grafted_date),
                                'cycle_ready_date' => $formatDate($summary['cycle_ready_date'] ?? null),
                                'lifecycle_status' => $lifecycle,
                                'lifecycle_label' => $summary['lifecycle_label'] ?? '',
                                'lifecycle_class' => $lifecycleClass,
                                'archived' => (bool) ($summary['archived'] ?? false),
                                'total_grafted' => $summary['total_grafted'] ?? 0,
                                'regraft' => $summary['regraft'] ?? 0,
                                'latest_regraft_date' => $formatDate($summary['latest_regraft_date'] ?? null),
                                'mortality' => $summary['mortality'] ?? 0,
                                'mortality_causes' => $summary['mortality_causes'] ?? [],
                                'available' => $summary['available'] ?? 0,
                                'surviving' => $summary['regraftable'] ?? 0,
                            ];
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="ps-4">
                                <div class="fw-medium">
                                    @if ($hasChildren)
                                        <button type="button" class="btn btn-sm btn-link p-0 me-1 align-baseline text-decoration-none"
                                            data-bs-toggle="collapse" data-bs-target=".child-of-{{ $batch->batch_id }}"
                                            aria-expanded="true" title="Toggle sub-batches">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    @elseif ($depth > 0)
                                        <span style="display:inline-block;width:{{ $depth * 18 }}px"></span><span class="text-muted">↳</span>
                                    @endif
                                    {{ $batch->batch_code }}
                                    @if ($batch->isSubBatch())
                                        <span class="badge bg-info-subtle text-info-emphasis ms-1">Regrafted</span>
                                    @endif
                                    @if ($parentMissing && $parentCode)
                                        <span class="text-muted small">(parent: {{ $parentCode }})</span>
                                    @endif
                                </div>
                                <div class="text-muted small">{{ $formatDate($batch->grafted_date) }}</div>
                                @if ($batch->isArchived())
                                    <div class="text-muted small fst-italic">Archived</div>
                                @endif
                            </td>
                            <td>{{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}</td>
                            <td class="text-end">
                                <div>{{ number_format($summary['total_grafted'] ?? 0) }}</div>
                                <div class="text-muted small">{{ $formatDate($batch->grafted_date) }}</div>
                            </td>
                            <td class="text-end">
                                <div class="text-warning fw-bold">{{ number_format($summary['regraft'] ?? 0) }}</div>
                                <div class="text-muted small">{{ $formatDate($summary['latest_regraft_date'] ?? null) }}</div>
                            </td>
                            <td class="text-end text-danger">{{ number_format($summary['mortality'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($summary['available'] ?? 0) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $lifecycleClass }}">{{ $summary['lifecycle_label'] ?? '' }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#viewBatchModal"
                                        data-details="{{ json_encode($rowPayload) }}"
                                        title="View batch details">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    @if (! $batch->isArchived() && auth()->user()->hasPermission('record_operations'))
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                            data-bs-toggle="dropdown" data-bs-config='{"popperConfig":{"strategy":"fixed"}}' aria-expanded="false">
                                            More
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                @if (($summary['regraftable'] ?? 0) > 0)
                                                    <button type="button" class="dropdown-item"
                                                        data-bs-toggle="modal" data-bs-target="#recordRegraftModal"
                                                        data-batch-id="{{ $batch->batch_id }}">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Regraft
                                                    </button>
                                                @else
                                                    <button type="button" class="dropdown-item disabled" disabled
                                                        title="Nothing to regraft for this batch">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Regraft
                                                    </button>
                                                @endif
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item"
                                                    data-bs-toggle="modal" data-bs-target="#recordMortalityModal"
                                                    data-batch-id="{{ $batch->batch_id }}">
                                                    <i class="bi bi-bandaid me-1"></i> Record Mortality
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No batches found for the current filters.
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

<!-- Batch details (read-only) — populated by JS from the row payload; the derived
     lifecycle status follows Batch::lifecycleStatus() (21-day cycle, PRD §2.6). -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Batch Details: <span id="viewBatchCode">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Status</span>
                    <span class="badge bg-secondary" id="viewStatus">&mdash;</span>
                    <span class="badge bg-secondary d-none" id="viewArchived">Archived</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted small text-uppercase mb-3">Batch Information</h6>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Parent Batch:</div>
                                <div class="col-6 text-end" id="viewParent">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Scion Variety:</div>
                                <div class="col-6 text-end" id="viewScionVariety">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Grafted Date:</div>
                                <div class="col-6 text-end" id="viewGraftedDate">&mdash;</div>
                            </div>
                            <div class="row">
                                <div class="col-6 text-muted small fw-bold">21-Day Ready Date:</div>
                                <div class="col-6 text-end" id="viewReadyDate">&mdash;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted small text-uppercase mb-3">Counts</h6>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Total Grafted:</div>
                                <div class="col-6 text-end" id="viewTotalGrafted">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Regraft:</div>
                                <div class="col-6 text-end text-warning fw-bold" id="viewRegraft">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Latest Regraft:</div>
                                <div class="col-6 text-end" id="viewLatestRegraft">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Mortality:</div>
                                <div class="col-6 text-end text-danger fw-bold" id="viewMortality">&mdash;</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted small fw-bold">Available:</div>
                                <div class="col-6 text-end" id="viewAvailable">&mdash;</div>
                            </div>
                            <div class="row">
                                <div class="col-6 text-muted small fw-bold">Surviving (releasable):</div>
                                <div class="col-6 text-end text-success fw-bold" id="viewSurviving">&mdash;</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recorded mortality causes behind the batch's mortality figure
                     (grouped from the real Mortality_Record rows — PRD FR-04/FR-08). -->
                <div class="border rounded p-3 mt-3">
                    <h6 class="text-muted small text-uppercase mb-3">Mortality Causes</h6>
                    <p class="text-muted small mb-0" id="viewMortalityCausesEmpty">
                        No mortality recorded for this batch.
                    </p>
                    <div class="table-responsive d-none" id="viewMortalityCausesWrapper">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-muted small fw-bold">CAUSE</th>
                                    <th class="text-end text-muted small fw-bold" style="width: 120px">QUANTITY</th>
                                </tr>
                            </thead>
                            <tbody id="viewMortalityCausesBody"></tbody>
                        </table>
                        <p class="text-muted small mb-0 mt-2">
                            Grouped from this batch's recorded Mortality entries.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if (auth()->user()->hasPermission('record_operations'))
<!-- Add Batch Modal — the same FR-06 form as the Batches page; posts to the single
     `operational.batches.manage.store` route and validates through `StoreBatchRequest`
     (error bag `batch`). Gated like the target route (server-side `record_operations`). -->
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
                            <label class="form-label text-muted small fw-bold" for="addBatchCode">Batch Code</label>
                            <input type="text" class="form-control" id="addBatchCode" value="{{ $nextBatchCode }}" disabled readonly>
                            <div class="form-text">Assigned automatically when the batch is saved.</div>
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
                            <label class="form-label text-muted small fw-bold" for="addGraftedDate">Grafted Date</label>
                            <input type="text" class="form-control" id="addGraftedDate" value="{{ now()->format('M d, Y') }}" disabled readonly>
                            <div class="form-text">Set automatically to today when the batch is saved.</div>
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

<!-- Record Mortality Modal — the same FR-04 form as the Mortality Tracking page; posts to the single
     `operational.mortality.records.store` route and validates through `StoreMortalityRecordRequest`
     (error bag `mortality`). Gated like the target route (server-side `record_operations`). -->
<div class="modal fade" id="recordMortalityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Record Mortality</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="invMortalityForm" method="POST" action="{{ route('operational.mortality.records.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="invMortalityBatch" class="form-label text-muted small fw-bold">Batch <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'mortality') is-invalid @enderror" id="invMortalityBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($activeBatches as $activeBatch)
                                @php $breakdown = $activeBatchBreakdowns[$activeBatch->batch_id] ?? null; @endphp
                                <option value="{{ $activeBatch->batch_id }}" @selected(old('batch_id') == $activeBatch->batch_id)>
                                    {{ $activeBatch->batch_code }} ({{ $activeBatch->scionVariety?->scion_variety_name ?? 'Not assigned' }})@if ($breakdown) — Original: {{ number_format($breakdown['original_quantity']) }} | Stock Out: {{ number_format($breakdown['stock_out_quantity']) }} | Remaining: {{ number_format($breakdown['remaining_quantity']) }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="invMortalityQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'mortality') is-invalid @enderror" id="invMortalityQuantity"
                                name="quantity" min="1" value="{{ old('quantity') }}" required>
                            @error('quantity', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="invMortalityDate" class="form-label text-muted small fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('recorded_at', 'mortality') is-invalid @enderror" id="invMortalityDate"
                                name="recorded_at" max="{{ now()->toDateString() }}" value="{{ old('recorded_at', now()->toDateString()) }}" required>
                            @error('recorded_at', 'mortality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="invMortalityCause" class="form-label text-muted small fw-bold">Cause of Death <span class="text-danger">*</span></label>
                        <select class="form-select @error('cause', 'mortality') is-invalid @enderror" id="invMortalityCause" name="cause" required>
                            <option value="" disabled @selected(old('cause') === null)>Select cause of death</option>
                            @foreach (\App\Models\MortalityRecord::CAUSES as $causeOption)
                                <option value="{{ $causeOption }}" @selected(old('cause') === $causeOption)>{{ $causeOption }}</option>
                            @endforeach
                        </select>
                        @error('cause', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 d-none" id="invMortalityCauseOtherWrapper">
                        <label for="invMortalityCauseOther" class="form-label text-muted small fw-bold">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('cause_other', 'mortality') is-invalid @enderror" id="invMortalityCauseOther"
                            name="cause_other" maxlength="200" value="{{ old('cause_other') }}" placeholder="Specify the cause of death">
                        @error('cause_other', 'mortality')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>A dead root stock cannot be regrafted — it is counted as mortality.
                        Mortality cannot exceed the batch's remaining quantity (original less Stock Out and prior mortality).
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="invMortalitySubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Regraft Modal — the same FR-05 form as the Regraft page; posts to the single
     `operational.regraft.records.store` route and validates through `StoreRegraftRecordRequest`
     (error bag `regraft`). Gated like the target route (server-side `record_operations`). -->
<div class="modal fade" id="recordRegraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Record Regraft</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('operational.regraft.records.store') }}" id="invRegraftForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Regrafted plants move to a new sub-batch (e.g. B001-R1) with its own 21-day cycle.
                    </div>

                    <div class="mb-3">
                        <label for="invRegraftBatch" class="form-label text-muted small fw-bold">Source Batch <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'regraft') is-invalid @enderror" id="invRegraftBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($regraftableBatches as $summary)
                                @php $regraftBatch = $summary['batch']; @endphp
                                <option value="{{ $regraftBatch->batch_id }}"
                                    data-code="{{ $regraftBatch->batch_code }}"
                                    data-variety-id="{{ $regraftBatch->scion_variety_id }}"
                                    data-regraftable="{{ $summary['regraftable'] }}"
                                    data-grafted-date="{{ $regraftBatch->grafted_date?->format('Y-m-d') }}"
                                    @selected(old('batch_id') == $regraftBatch->batch_id)>
                                    {{ $regraftBatch->batch_code }} ({{ $regraftBatch->scionVariety?->scion_variety_name ?? 'No scion' }}) — {{ $summary['regraftable'] }} regraftable
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'regraft')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if ($regraftableBatches->isEmpty())
                            <div class="form-text text-warning">No batches currently have a quantity available for regrafting.</div>
                        @endif
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="invRegraftQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'regraft') is-invalid @enderror" id="invRegraftQuantity"
                                name="quantity" min="1" value="{{ old('quantity') }}" required>
                            <div class="form-text" id="invRegraftQuantityHint"></div>
                            @error('quantity', 'regraft')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="invRegraftDate" class="form-label text-muted small fw-bold">Regraft Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('regraft_date', 'regraft') is-invalid @enderror" id="invRegraftDate"
                                name="regraft_date" max="{{ now()->toDateString() }}" value="{{ old('regraft_date', now()->toDateString()) }}" required>
                            @error('regraft_date', 'regraft')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="invRegraftScionVariety" class="form-label text-muted small fw-bold">New Sub-Batch Scion Variety <span class="text-danger">*</span></label>
                        <select class="form-select @error('scion_variety_id', 'regraft') is-invalid @enderror" id="invRegraftScionVariety" name="scion_variety_id" required>
                            <option value="" disabled @selected(old('scion_variety_id') === null)>Select Scion Variety</option>
                            @foreach ($activeScionVarieties as $scionVariety)
                                <option value="{{ $scionVariety->scion_variety_id }}" @selected(old('scion_variety_id') == $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Defaults to the source batch's variety; the sub-batch may use a different one.</div>
                        @error('scion_variety_id', 'regraft')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="invRegraftRemark" class="form-label text-muted small fw-bold">Remarks</label>
                        <textarea class="form-control" id="invRegraftRemark" name="remark" rows="2" maxlength="255" placeholder="Optional details...">{{ old('remark') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold" id="invRegraftSubmitBtn">Save Regraft</button>
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
        const addBatchModalEl = document.getElementById('addBatchModal');

        @if ($errors->batch->any())
            if (addBatchModalEl) {
                new bootstrap.Modal(addBatchModalEl).show();
            }
        @endif

        const mortalityModalEl = document.getElementById('recordMortalityModal');
        const regraftModalEl = document.getElementById('recordRegraftModal');

        @if ($errors->mortality->any())
            if (mortalityModalEl) {
                new bootstrap.Modal(mortalityModalEl).show();
            }
        @endif

        @if ($errors->regraft->any())
            if (regraftModalEl) {
                new bootstrap.Modal(regraftModalEl).show();
            }
        @endif

        // Mortality modal: prefill the clicked batch and keep the "Others"
        // description field in sync with the selected cause (same behaviour as
        // the Mortality Tracking page form).
        const mortalityBatchSelect = document.getElementById('invMortalityBatch');
        const mortalityCauseSelect = document.getElementById('invMortalityCause');
        const mortalityCauseOtherWrapper = document.getElementById('invMortalityCauseOtherWrapper');
        const mortalityCauseOtherInput = document.getElementById('invMortalityCauseOther');
        const othersCause = @json(\App\Models\MortalityRecord::CAUSE_OTHERS);

        const syncMortalityCauseOther = function () {
            if (!mortalityCauseSelect || !mortalityCauseOtherWrapper) {
                return;
            }

            const isOthers = mortalityCauseSelect.value === othersCause;
            mortalityCauseOtherWrapper.classList.toggle('d-none', !isOthers);

            if (mortalityCauseOtherInput) {
                mortalityCauseOtherInput.required = isOthers;
            }
        };

        if (mortalityCauseSelect) {
            mortalityCauseSelect.addEventListener('change', syncMortalityCauseOther);
            syncMortalityCauseOther();
        }

        if (mortalityModalEl && mortalityBatchSelect) {
            mortalityModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const batchId = button ? button.getAttribute('data-batch-id') : null;

                if (batchId) {
                    mortalityBatchSelect.value = batchId;
                }

                syncMortalityCauseOther();
            });
        }

        const mortalityForm = document.getElementById('invMortalityForm');
        if (mortalityForm) {
            mortalityForm.addEventListener('submit', function () {
                syncMortalityCauseOther();

                const button = document.getElementById('invMortalitySubmitBtn');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                }
            });
        }

        // Regraft modal: prefill the clicked batch, cap the quantity at the
        // batch's surviving balance and default the sub-batch variety to the
        // source's (same behaviour as the Regraft page form).
        const regraftBatchSelect = document.getElementById('invRegraftBatch');
        const regraftQuantityInput = document.getElementById('invRegraftQuantity');
        const regraftVarietySelect = document.getElementById('invRegraftScionVariety');
        const regraftDateInput = document.getElementById('invRegraftDate');
        const regraftHint = document.getElementById('invRegraftQuantityHint');

        const applyRegraftConstraints = function () {
            if (!regraftBatchSelect) {
                return;
            }

            const option = regraftBatchSelect.options[regraftBatchSelect.selectedIndex];
            const regraftable = option ? parseInt(option.getAttribute('data-regraftable') || '0', 10) : 0;
            const varietyId = option ? option.getAttribute('data-variety-id') : null;
            const graftedDate = option ? option.getAttribute('data-grafted-date') : null;

            if (regraftable > 0) {
                regraftQuantityInput.max = regraftable;
                regraftHint.textContent = 'Up to ' + regraftable + ' available for regrafting.';
            } else {
                regraftQuantityInput.removeAttribute('max');
                regraftHint.textContent = '';
            }

            if (graftedDate) {
                regraftDateInput.min = graftedDate;
            } else {
                regraftDateInput.removeAttribute('min');
            }

            if (varietyId) {
                regraftVarietySelect.value = varietyId;
            }
        };

        if (regraftBatchSelect) {
            regraftBatchSelect.addEventListener('change', applyRegraftConstraints);
        }

        if (regraftModalEl && regraftBatchSelect) {
            regraftModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const batchId = button ? button.getAttribute('data-batch-id') : null;

                if (batchId) {
                    regraftBatchSelect.value = batchId;
                }

                applyRegraftConstraints();
                regraftQuantityInput.focus();
            });
        }

        applyRegraftConstraints();

        const regraftForm = document.getElementById('invRegraftForm');
        if (regraftForm) {
            regraftForm.addEventListener('submit', function () {
                applyRegraftConstraints();

                const button = document.getElementById('invRegraftSubmitBtn');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                }
            });
        }

        const setText = function (id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value == null || value === '' ? '—' : value;
            }
        };

        const setNumber = function (id, value) {
            setText(id, Number(value || 0).toLocaleString());
        };

        const readPayload = function (button, attribute) {
            try {
                return JSON.parse(button.getAttribute(attribute) || '{}');
            } catch (error) {
                return {};
            }
        };

        // Read-only Batch Details modal (no extra queries).
        const viewModalEl = document.getElementById('viewBatchModal');

        if (viewModalEl) {
            viewModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                const details = readPayload(button, 'data-details');

                setText('viewBatchCode', details.batch_code);
                setText('viewParent', details.parent_code || '—');
                setText('viewScionVariety', details.scion_variety);
                setText('viewGraftedDate', details.grafted_date);
                setText('viewReadyDate', details.cycle_ready_date);
                setNumber('viewTotalGrafted', details.total_grafted);
                setNumber('viewRegraft', details.regraft);
                setText('viewLatestRegraft', details.latest_regraft_date);
                setNumber('viewMortality', details.mortality);
                setNumber('viewAvailable', details.available);
                setNumber('viewSurviving', details.surviving);

                // Recorded mortality causes (real Mortality_Record rows grouped
                // by cause server-side; built with textContent so a user-entered
                // "Others" description is never interpreted as markup).
                const causesBody = document.getElementById('viewMortalityCausesBody');
                const causesWrapper = document.getElementById('viewMortalityCausesWrapper');
                const causesEmpty = document.getElementById('viewMortalityCausesEmpty');
                const causes = Array.isArray(details.mortality_causes) ? details.mortality_causes : [];

                if (causesBody) {
                    causesBody.innerHTML = '';

                    causes.forEach(function (entry) {
                        const row = document.createElement('tr');

                        const causeCell = document.createElement('td');
                        causeCell.textContent = entry.cause;

                        const quantityCell = document.createElement('td');
                        quantityCell.className = 'text-end text-danger fw-bold';
                        quantityCell.textContent = Number(entry.quantity || 0).toLocaleString();

                        row.appendChild(causeCell);
                        row.appendChild(quantityCell);
                        causesBody.appendChild(row);
                    });
                }

                if (causesWrapper) {
                    causesWrapper.classList.toggle('d-none', causes.length === 0);
                }

                if (causesEmpty) {
                    causesEmpty.classList.toggle('d-none', causes.length > 0);
                }

                const status = document.getElementById('viewStatus');
                if (status) {
                    status.className = 'badge ' + (details.lifecycle_class || 'bg-secondary');
                    status.textContent = details.lifecycle_label || '—';
                }

                const archived = document.getElementById('viewArchived');
                if (archived) {
                    archived.classList.toggle('d-none', !details.archived);
                }
            });
        }

        const addBatchForm = document.getElementById('addBatchForm');

        if (addBatchForm) {
            addBatchForm.addEventListener('submit', function () {
                const button = document.getElementById('addBatchSubmitBtn');

                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                }
            });
        }
    });
</script>
@endpush
