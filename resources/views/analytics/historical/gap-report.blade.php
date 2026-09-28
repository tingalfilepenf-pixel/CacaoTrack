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
        <input type="hidden" name="scion_variety_id" value="{{ $filters['scion_variety_id'] ?? '' }}">
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
                <label class="form-label text-muted small fw-bold">Scion Variety</label>
                <select name="scion_variety_id" class="form-select">
                    <option value="">All Scion Varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(($filters['scion_variety_id'] ?? null) == $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @include('analytics.partials.batch-options', ['batches' => $batches, 'selected' => $filters['batch_id'] ?? null])
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.historical.gap-report') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <small class="text-muted d-block mt-2">
            Expected = batch total grafted; Available = live available balance; Pulled Out = stock-out quantity for the batch.
            <strong>Gap = effective mortality for the batch.</strong> Stock Out is already netted out of the live available
            balance on every validated release, so <em>total grafted − available − pulled out</em> is not a gap by itself; recorded
            mortality is the only quantity still unreflected in Available, i.e. how much of the live balance may be
            overstated until those deaths are reconciled.
            <br>
            Filtering by a batch matches that exact batch only; selecting a parent does not include its sub-batches.
        </small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm bg-primary text-white text-center py-4">
            <h5 class="text-uppercase text-white-50 mb-2">Total Gap</h5>
            <h1 class="display-4 fw-bold mb-0">{{ number_format($gap['totalGap']) }} <span class="fs-4">TREES</span></h1>
            <p class="mt-3 mb-0">Recorded mortality not yet reconciled out of the live available balance</p>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light">
        <div class="row g-3 align-items-stretch">
            <div class="col-lg-8">
                <span class="text-muted d-block small text-uppercase fw-bold mb-2">Live ledger context</span>
                <div class="d-flex flex-wrap gap-3 h-100">
                    <div class="p-3 bg-white border rounded flex-fill">
                        <span class="text-muted d-block small">Expected</span>
                        <strong class="fs-5">{{ number_format($gap['totalExpected']) }}</strong>
                    </div>
                    <div class="p-3 bg-white border rounded flex-fill">
                        <span class="text-muted d-block small">Available</span>
                        <strong class="fs-5 text-warning">{{ number_format($gap['totalActual']) }}</strong>
                    </div>
                    <div class="p-3 bg-white border rounded flex-fill">
                        <span class="text-muted d-block small">Pulled Out</span>
                        <strong class="fs-5 text-secondary">{{ number_format($gap['totalPulledOut']) }}</strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="p-3 bg-white border border-danger rounded text-center h-100 d-flex flex-column justify-content-center">
                    <span class="text-muted d-block small">Gap = Effective Mortality</span>
                    <strong class="fs-4 text-danger">{{ number_format($gap['totalGap']) }}</strong>
                    <span class="text-muted small">Recorded mortality not yet reconciled out of Available</span>
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
                            <th>Parent Batch</th>
                            <th>Scion Variety</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Available</th>
                            <th class="text-end">Pulled Out</th>
                            <th class="text-end">Gap<br><small class="fw-normal text-muted">Effective Mortality</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gap['rows'] as $row)
                            <tr>
                                <td>
                                    @if ($row['depth'] > 0)
                                        <span style="display:inline-block;width:{{ $row['depth'] * 18 }}px"></span><span class="text-muted">↳</span>
                                    @endif
                                    <strong>{{ $row['batch'] }}</strong>
                                    @if ($row['parent_missing'] && $row['parent'])
                                        <span class="text-muted small">(parent: {{ $row['parent'] }})</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $row['parent'] ?? '—' }}</td>
                                <td>{{ $row['variety'] }}</td>
                                <td class="text-end">{{ number_format($row['expected']) }}</td>
                                <td class="text-end">{{ number_format($row['actual']) }}</td>
                                <td class="text-end">{{ number_format($row['pulled_out']) }}</td>
                                <td class="text-end text-danger fw-bold">{{ number_format($row['gap']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="text-end">{{ number_format($gap['totalExpected']) }}</td>
                            <td class="text-end">{{ number_format($gap['totalActual']) }}</td>
                            <td class="text-end">{{ number_format($gap['totalPulledOut']) }}</td>
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
    $appliedVariety = ! empty($filters['scion_variety_id'])
        ? ($scionVarieties->firstWhere('scion_variety_id', (int) $filters['scion_variety_id'])?->scion_variety_name ?? '#'.$filters['scion_variety_id'])
        : 'All scion varieties';
@endphp

<!-- Print Gap Report Modal -->
<div class="modal fade" id="printGapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="{{ route('analytics.historical.gap-report.export') }}" target="_blank">
                <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
                <input type="hidden" name="scion_variety_id" value="{{ $filters['scion_variety_id'] ?? '' }}">
                <div class="modal-header">
                    <h5 class="modal-title">Print Gap Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">The printable PDF uses the filters currently applied on this page.</p>
                    <table class="table table-sm mb-3">
                        <tbody>
                            <tr>
                                <th class="text-muted small fw-normal" style="width: 40%;">Scion Variety</th>
                                <td>{{ $appliedVariety }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small fw-normal">Batch</th>
                                <td>{{ $appliedBatch }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>The PDF contains the per-batch expected vs available inventory, the pulled-out quantity and the gap (= effective mortality), plus grand totals.
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
