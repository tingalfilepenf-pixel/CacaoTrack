@extends('layouts.app')

@section('title', 'Gap Report')
@section('page_title', 'Gap Report')

@section('page_actions')
<div class="d-flex flex-wrap gap-2 justify-content-end">
    <a href="{{ route('analytics.historical.gap-report.export.csv', request()->query()) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#printGapModal">
        <i class="bi bi-printer"></i> Print Report
    </button>
    <form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
        @csrf
        <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_GAP_REPORT }}">
        <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
        <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
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
    <div class="card-body">
        <form class="row g-3" method="GET" action="{{ route('analytics.historical.gap-report') }}">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Variety</label>
                <select name="variety_id" class="form-select">
                    <option value="">All Varieties</option>
                    @foreach ($varieties as $variety)
                        <option value="{{ $variety->variety_id }}" @selected(($filters['variety_id'] ?? null) == $variety->variety_id)>{{ $variety->variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>{{ $batch->batch_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.historical.gap-report') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <small class="text-muted d-block mt-2">Expected = batch viable count; Actual = current inventory balance; Gap = expected − actual (never negative).</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm bg-primary text-white text-center py-4">
            <h5 class="text-uppercase text-white-50 mb-2">Total Gap</h5>
            <h1 class="display-4 fw-bold mb-0">{{ number_format($gap['totalGap']) }} <span class="fs-4">TREES</span></h1>
            <p class="mt-3 mb-0">Potential Replanting Requirement</p>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body bg-light text-center">
                <div class="d-flex justify-content-center align-items-center flex-column flex-md-row gap-3">
                    <div class="p-3 bg-white border rounded">
                        <span class="text-muted d-block small">Expected Inventory</span>
                        <strong class="fs-5">{{ number_format($gap['totalExpected']) }}</strong>
                    </div>
                    <i class="bi bi-arrow-right d-none d-md-block fs-3 text-muted"></i>
                    <i class="bi bi-arrow-down d-md-none fs-3 text-muted"></i>
                    <div class="p-3 bg-white border rounded border-warning">
                        <span class="text-muted d-block small">Actual Inventory</span>
                        <strong class="fs-5 text-warning">{{ number_format($gap['totalActual']) }}</strong>
                    </div>
                    <i class="bi bi-arrow-right d-none d-md-block fs-3 text-muted"></i>
                    <i class="bi bi-arrow-down d-md-none fs-3 text-muted"></i>
                    <div class="p-3 bg-white border rounded border-danger">
                        <span class="text-muted d-block small">Gap</span>
                        <strong class="fs-5 text-danger">{{ number_format($gap['totalGap']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0">Batch Gap Details</h6>
    </div>
    <div class="card-body p-0">
        @if (empty($gap['rows']))
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Batch</th>
                            <th>Variety</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Gap</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gap['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row['batch'] }}</strong></td>
                                <td>{{ $row['variety'] }}</td>
                                <td class="text-end">{{ number_format($row['expected']) }}</td>
                                <td class="text-end">{{ number_format($row['actual']) }}</td>
                                <td class="text-end text-danger fw-bold">{{ number_format($row['gap']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="text-end">{{ number_format($gap['totalExpected']) }}</td>
                            <td class="text-end">{{ number_format($gap['totalActual']) }}</td>
                            <td class="text-end text-danger fs-5">{{ number_format($gap['totalGap']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

@include('analytics.partials.generated-reports', [
    'reports' => $reports,
    'reportTitle' => 'Generated Gap Reports',
])

@php
    $appliedBatch = ! empty($filters['batch_id'])
        ? ($batches->firstWhere('batch_id', (int) $filters['batch_id'])?->batch_code ?? '#'.$filters['batch_id'])
        : 'All batches';
    $appliedVariety = ! empty($filters['variety_id'])
        ? ($varieties->firstWhere('variety_id', (int) $filters['variety_id'])?->variety_name ?? '#'.$filters['variety_id'])
        : 'All varieties';
@endphp

<!-- Print Gap Report Modal -->
<div class="modal fade" id="printGapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="{{ route('analytics.historical.gap-report.export') }}" target="_blank">
                <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
                <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
                <div class="modal-header">
                    <h5 class="modal-title">Print Gap Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">The printable PDF uses the filters currently applied on this page.</p>
                    <table class="table table-sm mb-3">
                        <tbody>
                            <tr>
                                <th class="text-muted small fw-normal" style="width: 40%;">Variety</th>
                                <td>{{ $appliedVariety }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small fw-normal">Batch</th>
                                <td>{{ $appliedBatch }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>The PDF contains the per-batch expected vs actual inventory and the gap, plus grand totals.
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
