@extends('layouts.app')

@section('title', 'Age Profile')
@section('page_title', 'Age Profile')

@section('page_actions')
<form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
    @csrf
    <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_AGE_PROFILE }}">
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
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Please correct the highlighted fields.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Filter Age Profile</h6>
    </div>
    <div class="card-body">
        <form class="row g-3" method="GET" action="{{ route('analytics.historical.age-profile') }}">
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
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">As Of Date</label>
                <input type="date" name="as_of" class="form-control" value="{{ $filters['as_of'] ?? '' }}">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('analytics.historical.age-profile') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <small class="text-muted d-block mt-2">Age is derived from each batch's grafted date to the selected reporting date. Inventory quantity is counted per age bucket.</small>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-primary">Inventory by Age Group</h6>
    </div>
    <div class="card-body">
        @if ($profile['total'] > 0)
            <div id="ageProfileChart" style="height: 400px; width: 100%;"></div>
        @else
            <p class="text-muted text-center my-5">No data available for the selected period.</p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if ($profile['total'] > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dom = document.getElementById('ageProfileChart');
    if (!dom || !window.echarts) return;

    const chart = window.echarts.init(dom);
    window.addEventListener('resize', () => chart.resize());

    chart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: @json($profile['labels']) },
        yAxis: { type: 'value', name: 'Number of Trees' },
        series: [{
            name: 'Inventory',
            type: 'bar',
            data: @json($profile['values']),
            itemStyle: { color: '#0d6efd' },
            label: { show: true, position: 'top' }
        }]
    });
});
</script>
@endif
@endpush
