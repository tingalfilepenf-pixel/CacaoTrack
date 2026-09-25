@extends('layouts.app')

@section('title', 'Inventory Management')
@section('page_title', 'Inventory Management')

@section('page_actions')
<button type="button" class="btn btn-primary" id="exportInventoryBtn">
    <i class="bi bi-download me-1"></i> Export Inventory
</button>
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
    $formatDateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A') : '—';
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
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
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
                <div class="text-muted small fw-bold text-uppercase">Total Available Inventory</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalAvailable) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Current balance for the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Active Batches</div>
                <div class="fs-3 fw-bold">{{ number_format($activeBatches) }}</div>
                <div class="text-muted small">Batches with stock still available</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Depleted Batches</div>
                <div class="fs-3 fw-bold text-secondary">{{ number_format($depletedBatches) }}</div>
                <div class="text-muted small">Batches with no remaining balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search Batch</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Enter batch code...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterVariety" class="form-label text-muted small fw-bold mb-1">Variety</label>
                <select class="form-select form-select-sm" id="filterVariety" name="variety_id">
                    <option value="">All Varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected(($filters['variety_id'] ?? null) == $variety->variety_id)>
                            {{ $variety->variety_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterStatus" class="form-label text-muted small fw-bold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                    <option value="depleted" @selected(($filters['status'] ?? null) === 'depleted')>Depleted</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill">Filter</button>
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Current Inventory by Batch</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH</th>
                        <th>VARIETY</th>
                        <th>GRAFTED</th>
                        <th>RE-GRAFT</th>
                        <th>MORTALITY</th>
                        <th>VIABLE</th>
                        <th>AVAILABLE</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        @php
                            $batch = $inventory->batch;
                            $mortality = $batch ? $batch->effectiveMortalityQuantity() : 0;
                            $replanted = $batch ? $batch->totalReplanted() : 0;
                            $available = (int) $inventory->current_quantity;
                            $inventoryStatus = $inventory->status;
                            $statusClass = $inventoryStatus === \App\Models\Inventory::STATUS_ACTIVE ? 'bg-success' : 'bg-secondary';
                            $details = [
                                'batch_code' => $batch?->batch_code ?? '—',
                                'variety' => $batch?->variety?->variety_name ?? '—',
                                'scion' => $batch?->scion?->scion_name ?? '—',
                                'pot_type' => $batch?->potType?->pot_name ?? '—',
                                'grafted_date' => $formatDate($batch?->grafted_date),
                                'batch_status' => $batch?->status ?? '—',
                                'total_grafted' => (int) ($batch?->total_grafted ?? 0),
                                'replanted' => $replanted,
                                'mortality' => $mortality,
                                'viable' => (int) ($batch?->viable_count ?? 0),
                                'available' => $available,
                                'inventory_status' => $inventoryStatus,
                                'last_update' => $formatDateTime($inventory->last_update),
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $batch?->batch_code ?? '—' }}</td>
                            <td>{{ $batch?->variety?->variety_name ?? '—' }}</td>
                            <td>{{ number_format((int) ($batch?->total_grafted ?? 0)) }}</td>
                            <td><span class="text-warning fw-bold">{{ number_format($replanted) }}</span></td>
                            <td><span class="text-danger">{{ number_format($mortality) }}</span></td>
                            <td><span class="fw-bold">{{ number_format((int) ($batch?->viable_count ?? 0)) }}</span></td>
                            <td><span class="fw-bold text-success">{{ number_format($available) }}</span></td>
                            <td><span class="badge {{ $statusClass }}">{{ ucfirst($inventoryStatus) }}</span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#batchDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View Details">
                                    <i class="bi bi-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No inventory records found for the current filters.
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
                                <h6 class="text-muted small text-uppercase mb-3">General Information</h6>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold">Batch Code:</div>
                                    <div class="col-7" id="detailsBatchCodeValue">&mdash;</div>
                                </div>
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
                                    <div class="col-5 text-muted small fw-bold">Date Grafted:</div>
                                    <div class="col-7" id="detailsGraftedDate">&mdash;</div>
                                </div>
                                <div class="row">
                                    <div class="col-5 text-muted small fw-bold">Batch Status:</div>
                                    <div class="col-7" id="detailsBatchStatus">&mdash;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="text-muted small text-uppercase mb-3">Inventory Status</h6>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold">Total Grafted:</div>
                                    <div class="col-7" id="detailsTotalGrafted">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold">Re-graft:</div>
                                    <div class="col-7 text-warning" id="detailsReplanted">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold">Mortality:</div>
                                    <div class="col-7 text-danger" id="detailsMortality">&mdash;</div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-5 text-muted small fw-bold">Viable:</div>
                                    <div class="col-7 text-success" id="detailsViable">&mdash;</div>
                                </div>
                                <hr>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold fs-6">Available:</div>
                                    <div class="col-7 text-primary fs-6 fw-bold" id="detailsAvailable">&mdash;</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small fw-bold">Inventory Status:</div>
                                    <div class="col-7" id="detailsInventoryStatus">&mdash;</div>
                                </div>
                                <div class="row">
                                    <div class="col-5 text-muted small fw-bold">Last Update:</div>
                                    <div class="col-7 text-muted small" id="detailsLastUpdate">&mdash;</div>
                                </div>
                            </div>
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
        const detailsModal = document.getElementById('batchDetailsModal');
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

                setText('detailsBatchCode', details.batch_code);
                setText('detailsBatchCodeValue', details.batch_code);
                setText('detailsVariety', details.variety);
                setText('detailsScion', details.scion);
                setText('detailsPotType', details.pot_type);
                setText('detailsGraftedDate', details.grafted_date);
                setText('detailsBatchStatus', details.batch_status);
                setText('detailsTotalGrafted', Number(details.total_grafted || 0).toLocaleString());
                setText('detailsReplanted', Number(details.replanted || 0).toLocaleString());
                setText('detailsMortality', Number(details.mortality || 0).toLocaleString());
                setText('detailsViable', Number(details.viable || 0).toLocaleString());
                setText('detailsAvailable', Number(details.available || 0).toLocaleString());
                setText('detailsLastUpdate', details.last_update);

                const statusEl = document.getElementById('detailsInventoryStatus');
                if (statusEl) {
                    const status = details.inventory_status === 'active' ? 'active' : 'depleted';
                    statusEl.innerHTML = '<span class="badge ' + (status === 'active' ? 'bg-success' : 'bg-secondary') + '">' +
                        status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
                }
            });
        }

        const exportBtn = document.getElementById('exportInventoryBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                const table = document.getElementById('inventoryTable');
                if (!table) {
                    return;
                }

                const rows = Array.from(table.querySelectorAll('tr'));
                const csv = rows.map(function (row) {
                    const cells = Array.from(row.querySelectorAll('th, td')).slice(0, 8);
                    return cells.map(function (cell) {
                        const text = (cell.innerText || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                        return '"' + text + '"';
                    }).join(',');
                }).join('\r\n');

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'inventory-' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        }
    });
</script>
@endpush
