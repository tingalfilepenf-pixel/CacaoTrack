@php
    $selectedBatchId = $selected ?? null;
@endphp
@foreach (\App\Support\BatchTree::flatten($batches) as $node)
    @php $optionBatch = $node['batch']; @endphp
    <option value="{{ $optionBatch->batch_id }}" @selected((string) $selectedBatchId === (string) $optionBatch->batch_id)>
        @if ($node['depth'] > 0)
            ↳ {{ $optionBatch->batch_code }} (parent {{ $node['parent_code'] }}@if ($optionBatch->scionVariety), {{ $optionBatch->scionVariety->scion_variety_name }}@endif)
        @else
            {{ $optionBatch->batch_code }}@if ($optionBatch->scionVariety) ({{ $optionBatch->scionVariety->scion_variety_name }})@endif
        @endif
    </option>
@endforeach
