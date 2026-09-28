@extends('layouts.app')

@section('title', 'Cacao Tree Stock Ledger')
@section('page_title', 'Cacao Tree Stock Ledger')

@php
    $sections = [
        'grafted' => 'Grafted',
        'mortality' => 'Mortality',
        'regraft' => 'Regraft',
        'ready' => 'Ready to Plant',
        'pullout' => 'Pullout',
    ];
    $varietyColumns = $grid['varieties'];
    $varietyCount = max(1, count($varietyColumns));
@endphp

@section('page_actions')
<div class="d-flex flex-wrap gap-2 justify-content-end">
    <a class="btn btn-outline-success" href="{{ route('analytics.stock-ledger.export.csv', request()->query()) }}">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
    <form method="GET" action="{{ route('analytics.stock-ledger.export') }}" target="_blank" class="d-inline">
        <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
        <input type="hidden" name="scion_variety_id" value="{{ $filters['scion_variety_id'] ?? '' }}">
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
        <input type="hidden" name="scion_variety_id" value="{{ $filters['scion_variety_id'] ?? '' }}">
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
                    @include('analytics.partials.batch-options', ['batches' => $batches, 'selected' => $filters['batch_id'] ?? null])
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Scion Variety</label>
                <select name="scion_variety_id" class="form-select">
                    <option value="">All Scion Varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(($filters['scion_variety_id'] ?? null) == $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Date To</label>
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
        <small class="text-muted d-block mt-2">
            Monthly grid grouped by month, with dynamic per-Scion-Variety columns. Seedlings = Stock In quantity (seedling-only receipts);
            Grafted = root-batch total grafted; Mortality / Regraft / Ready to Plant / Pullout, per scion variety.
            <span class="fst-italic">Ready to Plant currently maps to each batch's live available count (pending client confirmation).</span>
            Filtering by a batch matches that exact batch only; selecting a parent does not include its sub-batches.
        </small>
    </div>
</div>

@if (! $grid['hasData'])
    <div class="card">
        <div class="card-body">
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        </div>
    </div>
@else
    @foreach ($grid['months'] as $month)
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary">{{ $month['label'] }}</h6>
                <span class="text-muted small">{{ count($month['rows']) }} entr{{ count($month['rows']) === 1 ? 'y' : 'ies' }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle ledger-month-table">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th rowspan="2" class="align-middle">Date</th>
                                <th rowspan="2" class="align-middle">Description</th>
                                <th rowspan="2" class="align-middle text-end">Seedlings</th>
                                @foreach ($sections as $sectionLabel)
                                    <th colspan="{{ $varietyCount }}" class="text-center">{{ $sectionLabel }}</th>
                                @endforeach
                            </tr>
                            <tr class="text-center">
                                @foreach ($sections as $sectionKey => $sectionLabel)
                                    @if (empty($varietyColumns))
                                        <th class="text-end">—</th>
                                    @else
                                        @foreach ($varietyColumns as $variety)
                                            <th class="text-end small">{{ $variety['name'] }}</th>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($month['rows'] as $row)
                                <tr>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M d') }}</td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="text-end">{{ $row['seedlings'] > 0 ? number_format($row['seedlings']) : '—' }}</td>
                                    @foreach ($sections as $sectionKey => $sectionLabel)
                                        @if (empty($varietyColumns))
                                            <td class="text-end">—</td>
                                        @else
                                            @foreach ($varietyColumns as $variety)
                                                @php $value = (int) ($row[$sectionKey][$variety['id']] ?? 0); @endphp
                                                <td class="text-end {{ $sectionKey === 'mortality' && $value > 0 ? 'text-danger' : ($sectionKey === 'regraft' && $value > 0 ? 'text-warning' : '') }}">
                                                    {{ $value > 0 ? number_format($value) : '—' }}
                                                </td>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="2" class="text-start">TOTAL — {{ $month['label'] }}</td>
                                <td class="text-end">{{ number_format($month['totals']['seedlings']) }}</td>
                                @foreach ($sections as $sectionKey => $sectionLabel)
                                    @if (empty($varietyColumns))
                                        <td class="text-end">0</td>
                                    @else
                                        @foreach ($varietyColumns as $variety)
                                            <td class="text-end">{{ number_format((int) ($month['totals'][$sectionKey][$variety['id']] ?? 0)) }}</td>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card mb-4 border-success">
        <div class="card-header bg-white">
            <h6 class="mb-0 fw-bold text-success">Grand Total (all months)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle ledger-month-table">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th rowspan="2" class="align-middle">Date</th>
                            <th rowspan="2" class="align-middle">Description</th>
                            <th rowspan="2" class="align-middle text-end">Seedlings</th>
                            @foreach ($sections as $sectionLabel)
                                <th colspan="{{ $varietyCount }}" class="text-center">{{ $sectionLabel }}</th>
                            @endforeach
                        </tr>
                        <tr class="text-center">
                            @foreach ($sections as $sectionKey => $sectionLabel)
                                @if (empty($varietyColumns))
                                    <th class="text-end">—</th>
                                @else
                                    @foreach ($varietyColumns as $variety)
                                        <th class="text-end small">{{ $variety['name'] }}</th>
                                    @endforeach
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tfoot class="table-success fw-bold">
                        <tr>
                            <td colspan="2" class="text-start">GRAND TOTAL</td>
                            <td class="text-end">{{ number_format($grid['grandTotals']['seedlings']) }}</td>
                            @foreach ($sections as $sectionKey => $sectionLabel)
                                @if (empty($varietyColumns))
                                    <td class="text-end">0</td>
                                @else
                                    @foreach ($varietyColumns as $variety)
                                        <td class="text-end">{{ number_format((int) ($grid['grandTotals'][$sectionKey][$variety['id']] ?? 0)) }}</td>
                                    @endforeach
                                @endif
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endif

<p class="text-muted small fst-italic">
    Sub-batch quantities appear under Regraft, not Grafted. A regraft from a sub-batch is a further movement of the same
    plants; no cross-column total is implied.
</p>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-primary">Batch summary (nested)</h6>
        <span class="text-muted small">{{ count($grid['batchSummary']['rows']) }} batch{{ count($grid['batchSummary']['rows']) === 1 ? '' : 'es' }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle ledger-summary-table">
                <thead class="table-light">
                    <tr>
                        <th>Batch or sub-batch</th>
                        <th>Parent Batch</th>
                        <th>Scion Variety</th>
                        <th class="text-end">Total Grafted</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Regraft</th>
                        <th class="text-end">Mortality</th>
                        <th class="text-end">Stock Out</th>
                        <th>Date Grafted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grid['batchSummary']['rows'] as $row)
                        <tr>
                            <td class="fw-medium">
                                @if ($row['depth'] > 0)
                                    <span style="display:inline-block;width:{{ $row['depth'] * 18 }}px"></span><span class="text-muted">↳</span>
                                @endif
                                {{ $row['batch'] }}
                                @if ($row['parent_missing'] && $row['parent'])
                                    <span class="text-muted small">(parent: {{ $row['parent'] }})</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $row['parent'] ?? '—' }}</td>
                            <td>{{ $row['variety'] }}</td>
                            <td class="text-end">{{ number_format($row['total_grafted']) }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($row['available']) }}</td>
                            <td class="text-end text-warning">{{ number_format($row['regraft']) }}</td>
                            <td class="text-end text-danger">{{ number_format($row['mortality']) }}</td>
                            <td class="text-end">{{ number_format($row['stock_out']) }}</td>
                            <td class="text-muted">{{ $row['grafted_date'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No batches for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="text-end">{{ number_format($grid['batchSummary']['totals']['total_grafted']) }}</td>
                        <td class="text-end">{{ number_format($grid['batchSummary']['totals']['available']) }}</td>
                        <td class="text-end text-warning">{{ number_format($grid['batchSummary']['totals']['regraft']) }}</td>
                        <td class="text-end text-danger">{{ number_format($grid['batchSummary']['totals']['mortality']) }}</td>
                        <td class="text-end">{{ number_format($grid['batchSummary']['totals']['stock_out']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@include('analytics.partials.generated-reports', [
    'reports' => $reports,
    'reportTitle' => 'Generated Stock Ledger Reports',
])
@endsection
