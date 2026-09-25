@extends('layouts.app')

@section('title', 'Mortality Seasonality Heatmap')
@section('page_title', 'Mortality Seasonality Heatmap')

@section('page_actions')
<form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
    @csrf
    <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_SEASONALITY }}">
    <input type="hidden" name="variety_id" value="{{ $filters['variety_id'] ?? '' }}">
    <input type="hidden" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}">
    <button type="submit" class="btn btn-success">
        <i class="bi bi-file-earmark-arrow-down"></i> Generate Report
    </button>
</form>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <p class="text-muted mb-3">
            This heatmap displays effective mortality across cacao varieties over the months of the selected year.
            Darker colors indicate higher mortality.
        </p>
        <form class="row g-3" method="GET" action="{{ route('analytics.historical.seasonality') }}">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Year</label>
                <select name="year" class="form-select">
                    @forelse ($heatmap['years'] as $year)
                        <option value="{{ $year }}" @selected($heatmap['selectedYear'] == $year)>{{ $year }}</option>
                    @empty
                        <option value="{{ $heatmap['selectedYear'] }}">{{ $heatmap['selectedYear'] }}</option>
                    @endforelse
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
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>{{ $batch->batch_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.historical.seasonality') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-primary">Seasonality Heatmap — {{ $heatmap['selectedYear'] }}</h6>
    </div>
    <div class="card-body">
        @if ($heatmap['total'] > 0 && count($heatmap['varieties']) > 0)
            <div id="seasonalityHeatmapChart" style="height: 500px; width: 100%;"></div>
        @else
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if ($heatmap['total'] > 0 && count($heatmap['varieties']) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dom = document.getElementById('seasonalityHeatmapChart');
    if (!dom || !window.echarts) return;

    const months = @json($heatmap['months']);
    const varieties = @json($heatmap['varieties']);

    const chart = window.echarts.init(dom);
    window.addEventListener('resize', () => chart.resize());

    chart.setOption({
        tooltip: {
            position: 'top',
            formatter: function (params) {
                return varieties[params.data[1]] + '<br/>' + months[params.data[0]] + ': ' + params.data[2] + ' trees';
            }
        },
        grid: { top: '5%', bottom: '15%', left: '10%', right: '5%' },
        xAxis: { type: 'category', data: months, splitArea: { show: true } },
        yAxis: { type: 'category', data: varieties, splitArea: { show: true } },
        visualMap: {
            min: 0,
            max: {{ max($heatmap['max'], 1) }},
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: '0%',
            inRange: { color: ['#f8d7da', '#dc3545', '#721c24'] }
        },
        series: [{
            name: 'Mortality',
            type: 'heatmap',
            data: @json($heatmap['data']),
            label: { show: true },
            emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
        }]
    });
});
</script>
@endif
@endpush
