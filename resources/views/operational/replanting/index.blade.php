@extends('layouts.app')

@section('title', 'Replanting')
@section('page_title', 'Replanting')

@section('page_actions')
<button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#recordReplantingModal">
    <i class="bi bi-plus-lg me-1"></i> Record Replanting
</button>
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
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Replanting Deficit</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format($totalDeficit) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Trees that still need to be replanted for the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Replanted</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalReplanted) }}</div>
                <div class="text-muted small">Quantity replanted across the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Batches With Deficit</div>
                <div class="fs-3 fw-bold">{{ number_format($batchesWithDeficit) }}</div>
                <div class="text-muted small">Batches still awaiting replanting</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('operational.replanting') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($summaries as $summary)
                        <option value="{{ $summary['batch']->batch_id }}" @selected(($filters['batch_id'] ?? null) == $summary['batch']->batch_id)>
                            {{ $summary['batch']->batch_code }} ({{ $summary['batch']->scionVariety?->scion_variety_name ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                <a href="{{ route('operational.replanting') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Replanting Status by Batch -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Replanting Status by Batch</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">BATCH</th>
                        <th>SCION VARIETY</th>
                        <th>TOTAL MORTALITY</th>
                        <th>REPLANTED</th>
                        <th>REMAINING (DEFICIT)</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        @php
                            $batch = $summary['batch'];
                            $history = $summary['history']->map(fn ($record) => [
                                'date' => $record->replanting_date?->format('M j, Y'),
                                'quantity' => (int) $record->quantity,
                                'user' => $record->user?->name ?? 'Unknown user',
                                'remark' => $record->remark,
                            ])->values();
                            $deficit = $summary['deficit'];
                            $deficitClass = $deficit === 0 ? 'bg-success' : ($deficit <= 20 ? 'bg-warning text-dark' : 'bg-danger');
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium">{{ $batch->batch_code }}</td>
                            <td>{{ $batch->scionVariety?->scion_variety_name ?? '—' }}</td>
                            <td>{{ number_format($summary['effective_mortality']) }}</td>
                            <td><span class="text-success fw-bold">{{ number_format($summary['total_replanted']) }}</span></td>
                            <td>
                                <span class="badge {{ $deficitClass }} fs-6">{{ number_format($deficit) }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#historyModal"
                                    data-batch-code="{{ $batch->batch_code }}"
                                    data-history="{{ $history->toJson() }}"
                                    title="View History">
                                    <i class="bi bi-clock-history"></i> History
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No batches found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Record Replanting Modal -->
<div class="modal fade" id="recordReplantingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Record Replanting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('operational.replanting.records.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Recording a replanting will reduce the replanting deficit for the selected batch.
                    </div>

                    <div class="mb-3">
                        <label for="recordBatch" class="form-label text-muted small fw-bold">Batch</label>
                        <select class="form-select" id="recordBatch" name="batch_id" required>
                            <option value="" disabled @selected(old('batch_id') === null)>Select Batch</option>
                            @foreach ($replantableBatches as $summary)
                                <option value="{{ $summary['batch']->batch_id }}" @selected(old('batch_id') == $summary['batch']->batch_id)>
                                    {{ $summary['batch']->batch_code }} ({{ $summary['batch']->scionVariety?->scion_variety_name ?? '—' }}) — deficit: {{ $summary['deficit'] }}
                                </option>
                            @endforeach
                        </select>
                        @if ($replantableBatches->isEmpty())
                            <div class="form-text text-warning">No batches currently have a replanting deficit.</div>
                        @endif
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recordQuantity" class="form-label text-muted small fw-bold">Quantity Replanted</label>
                            <input type="number" class="form-control" id="recordQuantity" name="quantity" min="1" value="{{ old('quantity') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="recordDate" class="form-label text-muted small fw-bold">Replanting Date</label>
                            <input type="date" class="form-control" id="recordDate" name="replanting_date" value="{{ old('replanting_date', now()->toDateString()) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="recordRemark" class="form-label text-muted small fw-bold">Remarks</label>
                        <textarea class="form-control" id="recordRemark" name="remark" rows="2" maxlength="255" placeholder="Optional details...">{{ old('remark') }}</textarea>
                    </div>

                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Replanting cannot exceed the batch's remaining deficit (effective mortality minus already replanted).
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Save Replanting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Replanting History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Replanting History — <span id="historyBatch">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Recorded By</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody id="historyRows">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No replanting records.</td>
                            </tr>
                        </tbody>
                    </table>
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
        const historyModal = document.getElementById('historyModal');
        if (historyModal) {
            historyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                document.getElementById('historyBatch').textContent = button.getAttribute('data-batch-code') || '—';

                let records = [];
                try {
                    records = JSON.parse(button.getAttribute('data-history') || '[]');
                } catch (error) {
                    records = [];
                }

                const tbody = document.getElementById('historyRows');
                const escape = function (value) {
                    const div = document.createElement('div');
                    div.textContent = value == null ? '' : value;
                    return div.innerHTML;
                };

                if (!records.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No replanting records.</td></tr>';
                    return;
                }

                tbody.innerHTML = records.map(function (record) {
                    return '<tr>' +
                        '<td>' + escape(record.date) + '</td>' +
                        '<td class="fw-bold text-success">' + escape(record.quantity) + '</td>' +
                        '<td>' + escape(record.user) + '</td>' +
                        '<td>' + escape(record.remark) + '</td>' +
                        '</tr>';
                }).join('');
            });
        }
    });
</script>
@endpush
