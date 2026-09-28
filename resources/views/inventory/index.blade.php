@extends('layouts.app')

@section('title', 'Inventory')
@section('page_title', 'Inventory')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordInventoryModal">
    <i class="bi bi-calendar-plus me-1"></i> Record Monthly Inventory
</button>
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
    $formatDateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A') : '—';
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

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Grafted</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalGrafted) }}</div>
                <div class="text-muted small">Sum across the current snapshots</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Active Snapshots</div>
                <div class="fs-3 fw-bold">{{ number_format($activeSnapshots) }}</div>
                <div class="text-muted small">Snapshots with an available balance</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Depleted Snapshots</div>
                <div class="fs-3 fw-bold text-secondary">{{ number_format($depletedSnapshots) }}</div>
                <div class="text-muted small">Snapshots with no available balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search Batch</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Enter batch code...">
                </div>
            </div>
            <div class="col-md-3">
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
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                    <option value="depleted" @selected(($filters['status'] ?? null) === 'depleted')>Depleted</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterMonth" class="form-label text-muted small fw-bold mb-1">Month</label>
                <select class="form-select form-select-sm" id="filterMonth" name="month">
                    <option value="">All Months</option>
                    @foreach ($months as $number => $name)
                        <option value="{{ $number }}" @selected((string) ($filters['month'] ?? '') === (string) $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label for="filterYear" class="form-label text-muted small fw-bold mb-1">Year</label>
                <input type="number" class="form-control form-control-sm" id="filterYear" name="year" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Snapshots Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Monthly Inventory Snapshots</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">INVENTORY DATE</th>
                        <th>BATCH</th>
                        <th>SCION VARIETY</th>
                        <th class="text-end">TOTAL GRAFTED</th>
                        <th class="text-end">RE-GRAFT</th>
                        <th class="text-end">MORTALITY</th>
                        <th>STATUS</th>
                        <th>LAST UPDATE</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        @php
                            $batch = $inventory->batch;
                            $statusText = ucfirst($inventory->status);
                            $statusClass = $inventory->status === \App\Models\Inventory::STATUS_ACTIVE ? 'bg-success' : 'bg-secondary';
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $formatDate($inventory->inventory_date) }}</td>
                            <td class="fw-medium">{{ $batch?->batch_code ?? '—' }}</td>
                            <td>{{ $batch?->scionVariety?->scion_variety_name ?? 'Not assigned' }}</td>
                            <td class="text-end">{{ number_format((int) $inventory->total_grafted) }}</td>
                            <td class="text-end text-warning fw-bold">{{ number_format((int) $inventory->regraft) }}</td>
                            <td class="text-end text-danger">{{ number_format((int) $inventory->mortality) }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusText }}</span></td>
                            <td class="text-muted small">{{ $formatDateTime($inventory->last_update) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No inventory snapshots found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $inventories->firstItem() ?? 0 }} to {{ $inventories->lastItem() ?? 0 }} of {{ $inventories->total() }} entries
        </span>
        {{ $inventories->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Record Monthly Inventory Modal -->
<div class="modal fade" id="recordInventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Record Monthly Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="recordInventoryForm" method="POST" action="{{ route('inventory.snapshots.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="inventoryBatch">Batch <span class="text-danger">*</span></label>
                        <select class="form-select @error('batch_id', 'inventory') is-invalid @enderror" id="inventoryBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($batches as $batch)
                                <option value="{{ $batch->batch_id }}" @selected(old('batch_id') == $batch->batch_id)>
                                    {{ $batch->batch_code }} — {{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id', 'inventory')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="inventoryDate">Inventory Month <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="date" class="form-control @error('inventory_date', 'inventory') is-invalid @enderror" id="inventoryDate"
                            name="inventory_date" max="{{ now()->toDateString() }}" value="{{ old('inventory_date', now()->toDateString()) }}">
                        @error('inventory_date', 'inventory')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Defaults to today. One snapshot per batch per calendar month.</div>
                    </div>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Total Grafted, Re-graft and Mortality are captured from the batch's live operational
                        records when the snapshot is recorded.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="recordInventorySubmitBtn">Record Snapshot</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const recordModalEl = document.getElementById('recordInventoryModal');

        @if ($errors->inventory->any())
            new bootstrap.Modal(recordModalEl).show();
        @endif

        const form = document.getElementById('recordInventoryForm');
        if (form) {
            form.addEventListener('submit', function () {
                const btn = document.getElementById('recordInventorySubmitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }
    });
</script>
@endpush
