@extends('layouts.app')

@section('title', 'Operational Analytics')
@section('page_title', 'Operational Analytics')

@section('page_actions')
<div class="d-flex flex-wrap gap-2 justify-content-end">
    <a href="{{ route('analytics.export.csv') }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#printAnalyticsModal">
        <i class="bi bi-printer"></i> Print Analytics
    </button>
    <form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
        @csrf
        <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_OPERATIONAL }}">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Generate Report
        </button>
    </form>
</div>
@endsection

@section('content')
<div class="container-fluid px-0">
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

    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card bg-white h-100 border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted text-uppercase small fw-bold mb-1">Total Grafted</p>
                            <h2 class="mb-0 text-success">{{ number_format($totalGrafted) }}</h2>
                            <span class="text-muted small">Live population (Grafted &minus; Regraft)</span>
                            <br><span class="text-muted small">Each plant counted once, all batches</span>
                        </div>
                        <i class="bi bi-tree stat-card-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card stat-card bg-white h-100 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted text-uppercase small fw-bold mb-1">Total Active Inventory</p>
                            <h2 class="mb-0 text-primary">{{ number_format($totalActiveInventory) }}</h2>
                            <span class="text-muted small">Active trees (archived batches excluded)</span>
                            <br><span class="text-muted small">Archived: {{ number_format($totalArchivedInventory) }}</span>
                        </div>
                        <i class="bi bi-tree-fill stat-card-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card stat-card bg-white h-100 border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted text-uppercase small fw-bold mb-1">Crude Mortality Rate</p>
                            <h2 class="mb-0 text-danger">{{ number_format($crudeMortalityRate, 2) }}%</h2>
                            <span class="text-muted small">Effective mortality ÷ total grafted</span>
                        </div>
                        <i class="bi bi-heartbreak-fill stat-card-icon text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold text-primary">Mortality Trend (Last 6 Months)</h6>
                </div>
                <div class="card-body">
                    @if ($mortalityTrend['total'] > 0)
                        <div id="mortalityTrendChart" style="height: 300px; width: 100%;"></div>
                    @else
                        <p class="text-muted text-center my-5">No data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold text-primary">Inventory by Scion Variety</h6>
                </div>
                <div class="card-body">
                    @if (count($inventoryByScionVariety['labels']) > 0 && array_sum($inventoryByScionVariety['values']) > 0)
                        <div id="inventoryVarietyChart" style="height: 300px; width: 100%;"></div>
                    @else
                        <p class="text-muted text-center my-5">No data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold text-primary">Survival vs Mortality</h6>
                </div>
                <div class="card-body">
                    @if ($survivalVsMortality['totalGrafted'] > 0)
                        <div id="survivalMortalityChart" style="height: 300px; width: 100%;"></div>
                    @else
                        <p class="text-muted text-center my-5">No data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Generated Reports -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold text-primary">Recent Generated Reports</h6>
                </div>
                <div class="card-body p-0">
                    @if ($recentReports->isEmpty())
                        <p class="text-muted text-center my-4">No reports have been generated yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Report</th>
                                        <th>Period</th>
                                        <th>Generated By</th>
                                        <th>Generated At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentReports as $report)
                                        <tr>
                                            <td>{{ $report->typeLabelText() }}</td>
                                            <td>{{ $report->analysis_period }}</td>
                                            <td>{{ $report->user?->name ?? '—' }}</td>
                                            <td>{{ $report->generated_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Analytics Modal -->
<div class="modal fade" id="printAnalyticsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="{{ route('analytics.export') }}" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title">Print Operational Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        The printable PDF contains the current KPI cards and the tabular values behind each chart
                        (Mortality Trend, Inventory by Scion Variety, Survival vs Mortality).
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>All values are read live from the database (all time).
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const charts = [];

    function render(id, option) {
        const dom = document.getElementById(id);
        if (!dom || !window.echarts) return;
        const chart = window.echarts.init(dom);
        chart.setOption(option);
        charts.push(chart);
    }

    window.addEventListener('resize', () => charts.forEach((chart) => chart.resize()));

    @if ($mortalityTrend['total'] > 0)
    render('mortalityTrendChart', {
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: @json($mortalityTrend['labels']) },
        yAxis: { type: 'value', name: 'Trees' },
        series: [{
            name: 'Mortality',
            type: 'line',
            smooth: true,
            data: @json($mortalityTrend['values']),
            itemStyle: { color: '#dc3545' },
            areaStyle: { opacity: 0.1 }
        }]
    });
    @endif

    @if (count($inventoryByScionVariety['labels']) > 0 && array_sum($inventoryByScionVariety['values']) > 0)
    render('inventoryVarietyChart', {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: @json($inventoryByScionVariety['labels']) },
        yAxis: { type: 'value', name: 'Trees' },
        series: [{
            name: 'Inventory',
            type: 'bar',
            data: @json($inventoryByScionVariety['values']),
            itemStyle: { color: '#0d6efd' }
        }]
    });
    @endif

    @if ($survivalVsMortality['totalGrafted'] > 0)
    render('survivalMortalityChart', {
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { bottom: '0%' },
        series: [{
            name: 'Condition',
            type: 'pie',
            radius: ['40%', '70%'],
            itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
            label: { show: true, formatter: '{b}\n{c}' },
            data: [
                { value: {{ $survivalVsMortality['surviving'] }}, name: 'Surviving', itemStyle: { color: '#198754' } },
                { value: {{ $survivalVsMortality['mortality'] }}, name: 'Mortality', itemStyle: { color: '#dc3545' } }
            ]
        }]
    });
    @endif
});
</script>
@endpush
