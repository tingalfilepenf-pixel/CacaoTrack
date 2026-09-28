@php
    $used = $batch->hasOperationalRecords();
    $latestInventory = $batch->latestInventory;
    $stockOutCount = array_key_exists('stock_out_details_count', $batch->getAttributes())
        ? (int) $batch->stock_out_details_count
        : $batch->stockOutDetails()->count();
    $details = [
        'batch_code' => $batch->batch_code,
        'parent_code' => $parentCode,
        'scion_variety' => $batch->scionVariety?->scion_variety_name,
        'grafted_date' => $batch->grafted_date?->format('M d, Y'),
        'created_by' => $batch->user?->name,
        'created_at' => $batch->created_at?->format('M d, Y H:i'),
        'total_grafted' => (int) $batch->total_grafted,
        'available_count' => (int) $batch->available_count,
        'status' => $batch->status,
        'mortality' => (int) $batch->mortality_records_count,
        'replanting' => (int) $batch->replanting_records_count,
        'inventory_date' => $latestInventory?->inventory_date?->format('M d, Y'),
        'inventory_status' => $latestInventory?->status,
        'stock_outs' => $stockOutCount,
        'used' => $used,
    ];

    // Every nested row carries a class for each ancestor so a parent's toggle
    // collapses its whole subtree (Bootstrap collapse targets a class selector).
    $collapseClasses = collect($ancestors ?? [])
        ->map(fn ($ancestorId) => 'child-of-'.$ancestorId)
        ->implode(' ');
    $rowClass = $depth > 0 ? trim('collapse show '.$collapseClasses) : '';
@endphp

<tr class="{{ $rowClass }}">
    <td class="ps-4 fw-medium text-primary">
        @if ($hasChildren)
            <button type="button" class="btn btn-sm btn-link p-0 me-1 align-baseline text-decoration-none"
                data-bs-toggle="collapse" data-bs-target=".child-of-{{ $batch->batch_id }}"
                aria-expanded="true" title="Toggle sub-batches">
                <i class="bi bi-chevron-down"></i>
            </button>
        @elseif ($depth > 0)
            <span style="display:inline-block;width:{{ $depth * 18 }}px"></span><span class="text-muted">↳</span>
        @endif

        {{ $batch->batch_code }}

        @if ($batch->isSubBatch())
            <span class="badge bg-info-subtle text-info-emphasis ms-1">Sub-batch</span>
        @endif

        @if ($parentMissing && $parentCode)
            <span class="text-muted small">(parent: {{ $parentCode }})</span>
        @endif

        @if ($status === 'archived' && $batch->archived_at)
            <div class="text-muted small fw-normal">Archived {{ $batch->archived_at->format('M j, Y g:i A') }}</div>
        @endif
    </td>
    <td class="text-muted">{{ $parentCode ?? '—' }}</td>
    <td>{{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}</td>
    <td class="text-muted">{{ $batch->grafted_date?->format('M d, Y') ?? '—' }}</td>
    <td class="text-end">{{ number_format($batch->total_grafted) }}</td>
    <td class="text-end fw-bold text-success">{{ number_format($batch->available_count) }}</td>
    <td>
        @if ($batch->status === \App\Models\Batch::STATUS_ACTIVE)
            <span class="badge bg-success">Active</span>
        @else
            <span class="badge bg-secondary">Inactive</span>
        @endif
    </td>
    <td class="text-end pe-4 text-nowrap">
        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#batchDetailsModal"
            data-details="{{ json_encode($details) }}" title="View">
            <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editBatchModal"
            data-batch-id="{{ $batch->batch_id }}"
            data-batch-code="{{ $batch->batch_code }}"
            data-scion-variety-id="{{ $batch->scion_variety_id }}"
            data-total-grafted="{{ $batch->total_grafted }}"
            data-grafted-date="{{ $batch->grafted_date?->format('Y-m-d') }}"
            data-status="{{ $batch->status }}"
            data-used="{{ $used ? '1' : '0' }}" title="Edit">
            <i class="bi bi-pencil"></i>
        </button>
        @if ($status === 'archived')
            <form method="POST" action="{{ route('operational.batches.restore', $batch) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </form>
        @else
            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveBatchModal"
                data-batch-id="{{ $batch->batch_id }}"
                data-batch-code="{{ $batch->batch_code }}" title="Archive">
                <i class="bi bi-archive"></i>
            </button>
        @endif
    </td>
</tr>
