@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="container-fluid px-0">
    @if (count($quickActions) > 0)
        <!-- Permission-based Quick Actions -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach ($quickActions as $action)
                    <a href="{{ route($action['route']) }}" class="btn btn-sm {{ $action['class'] }}">
                        <i class="bi {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($canViewAnalytics)
    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <!-- Total Grafted -->
        <div class="col-xl-4 col-md-6 mb-4 mb-xl-0">
            <div class="card stat-card h-100 py-2 border-start border-4 border-success">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col me-2">
                            <div class="small fw-bold text-success text-uppercase mb-1">
                                Total Grafted
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ number_format($totalGrafted) }}</div>
                            <div class="text-muted small mt-1">Live population (Grafted &minus; Regraft)</div>
                            <div class="text-muted small">Each plant counted once, all batches</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-tree text-success fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Active Inventory -->
        <div class="col-xl-4 col-md-6 mb-4 mb-xl-0">
            <div class="card stat-card h-100 py-2 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col me-2">
                            <div class="small fw-bold text-primary text-uppercase mb-1">
                                Total Active Inventory
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ number_format($totalActiveInventory) }}</div>
                            <div class="text-muted small mt-1">Plants currently available</div>
                            <div class="text-muted small">Active batches only (archived excluded)</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-tree-fill text-primary fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crude Mortality Rate -->
        <div class="col-xl-4 col-md-6 mb-4 mb-xl-0">
            <div class="card stat-card h-100 py-2 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col me-2">
                            <div class="small fw-bold text-danger text-uppercase mb-1">
                                Crude Mortality Rate
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ number_format($crudeMortalityRate, 2) }}%</div>
                            <div class="text-muted small mt-1">Effective mortality &divide; total grafted</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-down-arrow text-danger fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Mortality Trend Chart -->
        <div class="col-xl-8 col-lg-7 mb-4 mb-lg-0">
            <div class="card h-100">
                <div class="card-header d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-dark">Mortality Trend (Last 6 Months)</h6>
                    <a href="{{ route('analytics.index') }}" class="btn btn-sm btn-outline-secondary">View Analytics</a>
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

        <!-- Survival vs Mortality -->
        <div class="col-xl-4 col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-dark">Survival vs Mortality</h6>
                </div>
                <div class="card-body">
                    @if ($survivalVsMortality['totalGrafted'] > 0)
                        <div id="survivalChart" style="height: 300px; width: 100%;"></div>
                    @else
                        <p class="text-muted text-center my-5">No data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @endif

    <!-- Charts Row 2 -->
    @if ($canViewAnalytics || $canViewActivity)
    <div class="row mb-4">
        @if ($canViewAnalytics)
        <!-- Inventory by Scion Variety -->
        <div class="col-xl-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="m-0 fw-bold text-dark">Inventory by Scion Variety</h6>
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
        @endif

        @if ($canViewActivity)
        <!-- Recent Activities -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">Recent Activities</h6>
                    <a href="{{ route('activity.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if ($recentActivities->isEmpty())
                        <p class="text-muted text-center my-4">No activities have been recorded yet.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recentActivities as $activity)
                                <li class="list-group-item d-flex justify-content-between align-items-start py-3">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">
                                            <span class="badge {{ $activity->typeBadgeClass() }} me-2">{{ $activity->typeLabel() }}</span>
                                            {{ $activity->description }}
                                        </div>
                                        <small class="text-muted">
                                            {{ $activity->batch?->batch_code ? 'Batch '.$activity->batch->batch_code : ($activity->user?->name ?? 'System') }}
                                        </small>
                                    </div>
                                    <small class="text-muted text-nowrap ms-2">{{ $activity->activity_date?->diffForHumans() }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif
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
            xAxis: { type: 'category', boundaryGap: false, data: @json($mortalityTrend['labels']) },
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

        @if ($survivalVsMortality['totalGrafted'] > 0)
        render('survivalChart', {
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: '0%', left: 'center' },
            series: [{
                name: 'Condition',
                type: 'pie',
                radius: ['40%', '70%'],
                itemStyle: { borderRadius: 4, borderColor: '#fff', borderWidth: 2 },
                label: { show: true, formatter: '{b}\n{c}' },
                data: [
                    { value: {{ $survivalVsMortality['surviving'] }}, name: 'Surviving', itemStyle: { color: '#198754' } },
                    { value: {{ $survivalVsMortality['mortality'] }}, name: 'Mortality', itemStyle: { color: '#dc3545' } }
                ]
            }]
        });
        @endif

        @if (count($inventoryByScionVariety['labels']) > 0 && array_sum($inventoryByScionVariety['values']) > 0)
        render('inventoryVarietyChart', {
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { type: 'value', name: 'Trees' },
            yAxis: { type: 'category', data: @json($inventoryByScionVariety['labels']) },
            series: [{
                name: 'Inventory',
                type: 'bar',
                data: @json($inventoryByScionVariety['values']),
                itemStyle: { color: '#198754' }
            }]
        });
        @endif
    });
</script>
@endpush
