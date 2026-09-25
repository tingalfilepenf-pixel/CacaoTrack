@extends('layouts.app')

@section('title', 'Cacao Tree Stock Ledger')
@section('page_title', 'Cacao Tree Stock Ledger')

@section('page_actions')
<div class="d-flex gap-2">
    <button class="btn btn-outline-success" type="button" id="exportLedger">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </button>
    <form method="GET" action="{{ route('analytics.stock-ledger.export') }}" target="_blank" class="d-inline">
        <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
        <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
        <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        <button type="submit" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print Ledger
        </button>
    </form>
    <form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
        @csrf
        <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_STOCK_LEDGER }}">
        <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
        <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
        <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-file-earmark-arrow-down"></i> Generate Report
        </button>
    </form>
</div>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Please correct the highlighted fields.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Filter Ledger</h6>
    </div>
    <div class="card-body">
        <form class="row g-3" method="GET" action="{{ route('analytics.stock-ledger') }}">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>{{ $batch->batch_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Variety</label>
                <select name="variety_id" class="form-select">
                    <option value="">All Varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected(($filters['variety_id'] ?? null) == $variety->variety_id)>{{ $variety->variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Grafted From</label>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Grafted To</label>
                <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.stock-ledger') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        @error('date_to')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($ledger->isEmpty())
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle" id="ledgerTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Batch</th>
                            <th>Variety</th>
                            <th>Scion</th>
                            <th class="text-end">Grafted</th>
                            <th class="text-end">Viable</th>
                            <th class="text-end">Available</th>
                            <th class="text-end">Mortality</th>
                            <th class="text-end">Replanting</th>
                            <th>Grafted Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledger as $batch)
                            @php
                                $available = $batch->inventory?->current_quantity ?? $batch->available_count;
                                $status = $batch->inventory?->derivedStatus() ?? $batch->status;
                            @endphp
                            <tr>
                                <td>{{ $batch->batch_id }}</td>
                                <td class="fw-bold">{{ $batch->batch_code }}</td>
                                <td>{{ $batch->variety?->variety_name ?? '—' }}</td>
                                <td>{{ $batch->scion?->scion_name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($batch->total_grafted) }}</td>
                                <td class="text-end">{{ number_format($batch->viable_count) }}</td>
                                <td class="text-end">{{ number_format($available) }}</td>
                                <td class="text-end text-danger">{{ number_format($batch->effectiveMortalityQuantity()) }}</td>
                                <td class="text-end text-warning">{{ number_format($batch->totalReplanted()) }}</td>
                                <td>{{ $batch->grafted_date?->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge {{ $status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $ledger->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

@include('analytics.partials.generated-reports', [
    'reports' => $reports,
    'reportTitle' => 'Generated Stock Ledger Reports',
])
@endsection

@push('scripts')
<script>
document.getElementById('exportLedger')?.addEventListener('click', function () {
    const table = document.getElementById('ledgerTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr')).map((tr) =>
        Array.from(tr.querySelectorAll('th, td')).map((cell) => '"' + cell.innerText.trim().replace(/"/g, '""') + '"').join(',')
    );

    const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'cacao-stock-ledger.csv';
    link.click();
    URL.revokeObjectURL(link.href);
});
</script>
@endpush
