@extends('layouts.app')

@section('title', 'Scion Management')
@section('page_title', 'Scions')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receiveScionModal">
    <i class="bi bi-box-arrow-in-down me-1"></i> Receive Scions
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

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Number of Scions</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($metrics['available']) }}</div>
                <div class="text-muted small">Current available scion stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Scions Received</div>
                <div class="fs-3 fw-bold text-info">{{ number_format($metrics['received']) }}</div>
                <div class="text-muted small">Total scion stock received (available + re-grafted)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Re-grafted Scions</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format($metrics['regrafted']) }}</div>
                <div class="text-muted small">Replanting quantity for these batches</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Rejected Scions</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($metrics['rejected']) }}</div>
                <div class="text-muted small">Effective mortality for these batches</div>
            </div>
        </div>
    </div>
</div>

<!-- Scion Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-scissors me-2"></i>Scion Stock</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="scionTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">SCION</th>
                        <th>NUMBER OF SCIONS</th>
                        <th class="text-end pe-4">BATCHES</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($scion)
                        <tr>
                            <td class="ps-4 fw-medium">Scion</td>
                            <td>
                                <span class="badge bg-success fs-6">{{ number_format($scion->quantity) }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <span class="badge {{ $batchCount > 0 ? 'bg-success' : 'bg-secondary' }}">{{ number_format($batchCount) }}</span>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="3" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No scion stock has been recorded yet. Use <strong>Receive Scions</strong> to add stock.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Receive Scions Modal -->
<div class="modal fade" id="receiveScionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Receive Scions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="receiveScionForm" method="POST" action="{{ route('inventory.scions.receive') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="receiveScionQuantity">Quantity of Scions Received <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('quantity', 'scion') is-invalid @enderror" id="receiveScionQuantity"
                            name="quantity" min="1" step="1" required value="{{ old('quantity') }}" placeholder="e.g. 100">
                        @error('quantity', 'scion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">New scion stock is added to the current available balance.</div>
                    </div>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Current available balance:
                        <strong>{{ number_format($metrics['available']) }}</strong> scion(s).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="receiveScionSubmitBtn">Add to Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const receiveModalEl = document.getElementById('receiveScionModal');

        @if ($errors->scion->any())
            new bootstrap.Modal(receiveModalEl).show();
        @endif

        const form = document.getElementById('receiveScionForm');
        const button = document.getElementById('receiveScionSubmitBtn');

        if (form && button) {
            form.addEventListener('submit', function () {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
            });
        }
    });
</script>
@endpush
