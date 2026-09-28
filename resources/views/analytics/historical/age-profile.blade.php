@extends('layouts.app')

@section('title', 'Age Profile')
@section('page_title', 'Age Profile')

@section('page_actions')
<form method="POST" action="{{ route('analytics.reports.store') }}" class="d-inline">
    @csrf
    <input type="hidden" name="analysis_type" value="{{ \App\Models\Analytic::TYPE_AGE_PROFILE }}">
    <input type="hidden" name="scion_variety_id" value="{{ $filters['scion_variety_id'] ?? '' }}">
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
                <label class="form-label text-muted small fw-bold">Scion Variety</label>
                <select name="scion_variety_id" class="form-select">
                    <option value="">All Scion Varieties</option>
                    @foreach ($scionVarieties as $scionVariety)
                        <option value="{{ $scionVariety->scion_variety_id }}" @selected(($filters['scion_variety_id'] ?? null) == $scionVariety->scion_variety_id)>{{ $scionVariety->scion_variety_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    @include('analytics.partials.batch-options', ['batches' => $batches, 'selected' => $filters['batch_id'] ?? null])
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
        <small class="text-muted d-block mt-2">Age is derived from each batch's grafted date to the selected reporting date. Each batch's current available quantity is counted per age bucket. Filtering by a batch matches that exact batch only; selecting a parent does not include its sub-batches.</small>
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

<div class="card mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-primary">Batch age detail</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Batch or sub-batch</th>
                        <th>Parent Batch</th>
                        <th>Scion Variety</th>
                        <th>Date Grafted</th>
                        <th class="text-end">Age (days)</th>
                        <th class="text-end">Available</th>
                        <th>Bucket</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profile['rows'] as $row)
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
                            <td class="text-muted">{{ $row['grafted_date'] ?? '—' }}</td>
                            <td class="text-end">{{ $row['age'] ?? '—' }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($row['available']) }}</td>
                            <td>{{ $row['bucket'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No batches for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
