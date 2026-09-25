@extends('layouts.app')

@section('title', 'Mortality Root-Cause Matrix')
@section('page_title', 'Mortality Root-Cause Matrix')

@section('page_actions')
<div class="d-flex flex-wrap gap-2 justify-content-end">
    <a href="{{ route('analytics.mortality-matrix.export.csv', request()->query()) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#printMatrixModal">
        <i class="bi bi-printer"></i> Print Matrix
    </button>
    <form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
        @csrf
        <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_MORTALITY_MATRIX }}">
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
        <h6 class="mb-0">Matrix Filters</h6>
    </div>
    <div class="card-body">
        <form class="row g-3" method="GET" action="{{ route('analytics.mortality-matrix') }}">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Variety</label>
                <select name="variety_id" class="form-select">
                    <option value="">All Varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected(($filters['variety_id'] ?? null) == $variety->variety_id)>{{ $variety->variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>{{ $batch->batch_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.mortality-matrix') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        @error('date_to')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>
</div>

@php
    $causeAlerts = collect($alerts['causes'] ?? [])->keyBy('cause');
    $cellAlerts = collect($alerts['cells'] ?? [])->keyBy(fn ($cell) => $cell['variety'].'|'.$cell['cause']);
    $hasRegression = ($alerts['hasBaseline'] ?? false) && ($causeAlerts->isNotEmpty() || $cellAlerts->isNotEmpty());
@endphp

@if ($hasRegression)
    <div class="alert alert-warning border-warning" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading fw-bold mb-1">Mortality increase detected</h6>
                <p class="mb-2 small">
                    Effective mortality for the applied filters is higher than the most recent saved
                    Root-Cause Matrix report
                    @if ($matrixBaseline?->generated_at)
                        ({{ $matrixBaseline->generated_at->format('M d, Y H:i') }})
                    @endif
                    — compared against the previously recorded counts.
                </p>
                @if ($causeAlerts->isNotEmpty())
                    <ul class="mb-0 small">
                        @foreach ($causeAlerts as $alert)
                            <li>
                                <strong>{{ $alert['cause'] }}</strong>:
                                {{ number_format($alert['previous']) }} &rarr; {{ number_format($alert['current']) }}
                                (<span class="text-danger fw-bold">+{{ number_format($alert['increase']) }}</span>)
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Raw Mortality</div>
                <div class="fs-3 fw-bold">{{ number_format($matrix['rawTotal']) }}</div>
                <div class="text-muted small">Recorded quantity before adjustments</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Adjustments</div>
                <div class="fs-3 fw-bold {{ $matrix['adjustmentTotal'] < 0 ? 'text-success' : ($matrix['adjustmentTotal'] > 0 ? 'text-danger' : 'text-muted') }}">
                    {{ $matrix['adjustmentTotal'] > 0 ? '+' : '' }}{{ number_format($matrix['adjustmentTotal']) }}
                </div>
                <div class="text-muted small">Signed corrections from mortality adjustments</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Effective Mortality</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($matrix['grandTotal']) }}</div>
                <div class="text-muted small">Raw + adjustments (matches Mortality Tracking)</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if (empty($matrix['rows']))
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        @else
            <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Variety</th>
                            @foreach ($matrix['causes'] as $cause)
                                @php $causeAlert = $causeAlerts->get($cause); @endphp
                                <th class="{{ $causeAlert ? 'table-danger' : '' }}">
                                    {{ $cause }}
                                    @if ($causeAlert)
                                        <div class="small fw-normal text-danger">+{{ number_format($causeAlert['increase']) }}</div>
                                    @endif
                                </th>
                            @endforeach
                            <th class="bg-light fw-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix['rows'] as $row)
                            <tr>
                                <td class="text-start fw-bold">{{ $row['variety'] }}</td>
                                @foreach ($matrix['causes'] as $cause)
                                    @php
                                        $effective = $row['cells'][$cause] ?? 0;
                                        $raw = $row['rawCells'][$cause] ?? 0;
                                        $adjustment = $row['adjustmentCells'][$cause] ?? 0;
                                        $cellAlert = $cellAlerts->get($row['variety'].'|'.$cause);
                                    @endphp
                                    <td class="{{ $cellAlert ? 'table-danger' : '' }}">
                                        {{ number_format($effective) }}
                                        @if ($cellAlert)
                                            <div class="small fw-bold text-danger">
                                                <i class="bi bi-arrow-up-short"></i>{{ number_format($cellAlert['previous']) }} &rarr; {{ number_format($cellAlert['current']) }}
                                            </div>
                                        @endif
                                        @if ($adjustment !== 0)
                                            <div class="text-muted small">
                                                base {{ number_format($raw) }}
                                                <span class="{{ $adjustment < 0 ? 'text-success' : 'text-danger' }}">
                                                    ({{ $adjustment > 0 ? '+' : '' }}{{ number_format($adjustment) }})
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="bg-light fw-bold text-danger">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td class="text-start">Grand Total</td>
                            @foreach ($matrix['causes'] as $cause)
                                @php $causeAlert = $causeAlerts->get($cause); @endphp
                                <td class="{{ $causeAlert ? 'table-danger' : '' }}">
                                    {{ number_format($matrix['totalsByCause'][$cause] ?? 0) }}
                                </td>
                            @endforeach
                            <td class="text-danger fs-5">{{ number_format($matrix['grandTotal']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

@include('analytics.partials.generated-reports', [
    'reports' => $reports,
    'reportTitle' => 'Generated Root-Cause Reports',
])

@php
    $appliedPeriod = ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null)
        ? (($filters['date_from'] ?? 'Any').' to '.($filters['date_to'] ?? 'Any'))
        : 'All time';
    $appliedBatch = ! empty($filters['batch_id'])
        ? ($batches->firstWhere('batch_id', (int) $filters['batch_id'])?->batch_code ?? '#'.$filters['batch_id'])
        : 'All batches';
    $appliedVariety = ! empty($filters['variety_id'])
        ? ($varieties->firstWhere('variety_id', (int) $filters['variety_id'])?->variety_name ?? '#'.$filters['variety_id'])
        : 'All varieties';
@endphp

<!-- Print Matrix Modal -->
<div class="modal fade" id="printMatrixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="{{ route('analytics.mortality-matrix.export') }}" target="_blank">
                <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
                <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                <div class="modal-header">
                    <h5 class="modal-title">Print Mortality Root-Cause Matrix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">The printable PDF uses the filters currently applied on this page.</p>
                    <table class="table table-sm mb-3">
                        <tbody>
                            <tr>
                                <th class="text-muted small fw-normal" style="width: 40%;">Period</th>
                                <td>{{ $appliedPeriod }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small fw-normal">Variety</th>
                                <td>{{ $appliedVariety }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small fw-normal">Batch</th>
                                <td>{{ $appliedBatch }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>The PDF contains the real matrix (raw &rarr; adjustments &rarr; effective) and grand totals for the selected filters.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-printer me-1"></i> Generate Printout
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
