@extends('layouts.app')

@section('title', 'Scion Receiving')
@section('page_title', 'Scion Receiving')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScionReceivingModal">
    <i class="bi bi-plus-lg me-1"></i> Add Scion Receiving
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

<div class="alert alert-info d-flex align-items-start" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <div>
        Scion receiving is a log of scion material received. It does not change any stock balance and is not a ledger.
    </div>
</div>

<!-- Scion Receivings Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Scion Receiving Log</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="scionReceivingTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">DATE</th>
                        <th>SCION VARIETY</th>
                        <th>SUPPLIER</th>
                        <th>QUANTITY</th>
                        <th>UNIT PRICE</th>
                        <th class="pe-4">ORIGIN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receivings as $receiving)
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $receiving->date_received?->format('M j, Y') ?? '—' }}</td>
                            <td class="fw-medium">{{ $receiving->scionVariety?->scion_variety_name ?? '—' }}</td>
                            <td>{{ $receiving->supplier?->supplier_name ?? '—' }}</td>
                            <td>{{ number_format($receiving->quantity) }}</td>
                            <td>{{ $receiving->unit_price !== null ? '₱'.number_format((float) $receiving->unit_price, 2) : '—' }}</td>
                            <td class="pe-4">{{ $receiving->origin ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No scion receiving records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $receivings->firstItem() ?? 0 }} to {{ $receivings->lastItem() ?? 0 }} of {{ $receivings->total() }} entries
        </span>
        {{ $receivings->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Scion Receiving Modal -->
<div class="modal fade" id="addScionReceivingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Scion Receiving</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addScionReceivingForm" method="POST" action="{{ route('operational.scion-receivings.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionReceivingVariety">Scion Variety <span class="text-danger">*</span></label>
                        <select class="form-select @error('scion_variety_id', 'scion_receiving') is-invalid @enderror" id="addScionReceivingVariety"
                            name="scion_variety_id" required>
                            <option value="" disabled {{ old('scion_variety_id') ? '' : 'selected' }}>Select a scion variety...</option>
                            @foreach ($scionVarieties as $scionVariety)
                                <option value="{{ $scionVariety->scion_variety_id }}" @selected(old('scion_variety_id') == $scionVariety->scion_variety_id)>
                                    {{ $scionVariety->scion_variety_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('scion_variety_id', 'scion_receiving')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionReceivingSupplier">Supplier <span class="text-muted fw-normal">(optional)</span></label>
                        <select class="form-select @error('supplier_id', 'scion_receiving') is-invalid @enderror" id="addScionReceivingSupplier"
                            name="supplier_id">
                            <option value="">None</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}" @selected(old('supplier_id') == $supplier->supplier_id)>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id', 'scion_receiving')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addScionReceivingQuantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('quantity', 'scion_receiving') is-invalid @enderror" id="addScionReceivingQuantity"
                                name="quantity" min="1" step="1" required value="{{ old('quantity') }}" placeholder="e.g. 100">
                            @error('quantity', 'scion_receiving')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addScionReceivingUnitPrice">Unit Price <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="number" class="form-control @error('unit_price', 'scion_receiving') is-invalid @enderror" id="addScionReceivingUnitPrice"
                                name="unit_price" min="0" step="0.01" value="{{ old('unit_price') }}" placeholder="e.g. 12.50">
                            @error('unit_price', 'scion_receiving')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionReceivingDate">Date Received <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date_received', 'scion_receiving') is-invalid @enderror" id="addScionReceivingDate"
                            name="date_received" max="{{ now()->toDateString() }}" required value="{{ old('date_received') }}">
                        @error('date_received', 'scion_receiving')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionReceivingOrigin">Origin <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control @error('origin', 'scion_receiving') is-invalid @enderror" id="addScionReceivingOrigin"
                            name="origin" maxlength="255" value="{{ old('origin') }}" placeholder="e.g. RU, banban">
                        @error('origin', 'scion_receiving')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addScionReceivingNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control @error('notes', 'scion_receiving') is-invalid @enderror" id="addScionReceivingNotes"
                            name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                        @error('notes', 'scion_receiving')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addScionReceivingSubmitBtn">Save Scion Receiving</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const addModalEl = document.getElementById('addScionReceivingModal');

        @if ($errors->scion_receiving->any())
            new bootstrap.Modal(addModalEl).show();
        @endif

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

        guardSubmit('addScionReceivingForm', 'addScionReceivingSubmitBtn', 'Saving...');
    });
</script>
@endpush
