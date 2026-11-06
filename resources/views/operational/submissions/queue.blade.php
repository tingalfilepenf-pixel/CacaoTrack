@extends('layouts.app')

@section('title', 'Submission Queue')
@section('page_title', '')
@section('page_actions', '')

@section('content')
@php
    $typeMortality = \App\Models\OperationalSubmission::TYPE_MORTALITY;
    $typeStockOut = \App\Models\OperationalSubmission::TYPE_STOCK_OUT;
    $typeStockIn = \App\Models\OperationalSubmission::TYPE_STOCK_IN;
    $typeBatchCreation = \App\Models\OperationalSubmission::TYPE_BATCH_CREATION;

    $statusLabels = [
        \App\Models\OperationalSubmission::STATUS_PENDING => 'Pending',
        \App\Models\OperationalSubmission::STATUS_APPROVED => 'Approved',
        \App\Models\OperationalSubmission::STATUS_REJECTED => 'Rejected',
    ];

    $statusClasses = [
        \App\Models\OperationalSubmission::STATUS_PENDING => 'status-pending',
        \App\Models\OperationalSubmission::STATUS_APPROVED => 'status-approved',
        \App\Models\OperationalSubmission::STATUS_REJECTED => 'status-rejected',
    ];

    $statusIcons = [
        \App\Models\OperationalSubmission::STATUS_PENDING => 'bi-hourglass-split',
        \App\Models\OperationalSubmission::STATUS_APPROVED => 'bi-check-circle-fill',
        \App\Models\OperationalSubmission::STATUS_REJECTED => 'bi-x-circle-fill',
    ];

    $typeIcons = [
        $typeMortality => 'bi-tree',
        $typeStockOut => 'bi-box-arrow-right',
        $typeStockIn => 'bi-box-arrow-in-right',
        $typeBatchCreation => 'bi-collection',
    ];

    $activeTypeFilter = $typeFilter !== '';

    $tabParams = fn (string $status): array => array_filter([
        'status' => $status,
        'q' => $search !== '' ? $search : null,
        'type' => $typeFilter !== '' ? $typeFilter : null,
    ], fn ($value) => $value !== null && $value !== '');

    $subjectLabel = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeStockIn, $typeBatchCreation, $clientNames, $supplierNames): string {
        if ($submission->submission_type === $typeMortality) {
            return 'Mortality';
        }

        if ($submission->submission_type === $typeStockOut) {
            $clientName = $clientNames[(int) ($submission->payload['client_id'] ?? 0)] ?? null;

            return 'Stock Out for '.($clientName ?? 'Client');
        }

        if ($submission->submission_type === $typeStockIn) {
            $suppliers = collect([
                $supplierNames[(int) ($submission->payload['seedling']['supplier_id'] ?? 0)] ?? null,
                $supplierNames[(int) ($submission->payload['scion']['supplier_id'] ?? 0)] ?? null,
            ])->filter()->unique()->values();

            return $suppliers->isEmpty() ? 'Stock In' : 'Stock In from '.$suppliers->implode(' + ');
        }

        if ($submission->submission_type === $typeBatchCreation) {
            if (($submission->payload['source'] ?? null) === \App\Models\Batch::SOURCE_SUPPLIER) {
                $supplierName = $supplierNames[(int) ($submission->payload['supplier_id'] ?? 0)] ?? null;

                return 'Supplier delivery'.($supplierName ? ' from '.$supplierName : '');
            }

            return 'Own nursery batch';
        }

        return $submission->typeLabel();
    };

    $batchCodeLabel = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeBatchCreation, $batchCodes, $officialBatchCodes): string {
        if ($submission->submission_type === $typeMortality) {
            $batchId = (int) ($submission->subject_batch_id ?? ($submission->payload['batch_id'] ?? 0));

            return $batchCodes[$batchId] ?? '—';
        }

        if ($submission->submission_type === $typeStockOut) {
            $codes = collect($submission->payload['details'] ?? [])
                ->pluck('batch_id')
                ->map(fn ($id) => $batchCodes[(int) $id] ?? null)
                ->filter()
                ->unique()
                ->values();

            if ($codes->isEmpty()) {
                return '—';
            }

            return $codes->count() > 2
                ? $codes->take(2)->implode(', ').' +'.($codes->count() - 2)
                : $codes->implode(', ');
        }

        if ($submission->submission_type === $typeBatchCreation) {
            return $officialBatchCodes[$submission->submission_id] ?? 'Assigned at approval';
        }

        return '—';
    };

    $detailsPrimary = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeStockIn, $typeBatchCreation, $batchCodeLabel): string {
        if ($submission->submission_type === $typeMortality) {
            return 'Batch: '.$batchCodeLabel($submission).' | Quantity: '.number_format((int) ($submission->payload['quantity'] ?? 0));
        }

        if ($submission->submission_type === $typeStockOut) {
            $quantity = collect($submission->payload['details'] ?? [])->sum('quantity');

            return 'Batch: '.$batchCodeLabel($submission).' | Quantity: '.number_format((int) $quantity);
        }

        if ($submission->submission_type === $typeStockIn) {
            return $submission->stockInSummary();
        }

        if ($submission->submission_type === $typeBatchCreation) {
            if (($submission->payload['source'] ?? null) === \App\Models\Batch::SOURCE_SUPPLIER) {
                return 'Plants delivered: '.number_format((int) ($submission->payload['plants_delivered'] ?? 0));
            }

            return 'Plants grafted: '.number_format((int) ($submission->payload['plants_grafted'] ?? 0))
                .' | Seedlings: '.number_format((int) ($submission->payload['seedlings_used'] ?? 0))
                .' | Scion: '.number_format((int) ($submission->payload['scion_used'] ?? 0));
        }

        return '';
    };

    $detailsSecondary = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeStockIn, $typeBatchCreation, $clientNames, $supplierNames, $varietyNames): string {
        if ($submission->submission_type === $typeMortality) {
            $cause = $submission->payload['cause_other'] ?: ($submission->payload['cause'] ?? null);

            return 'Remarks: '.($cause ?: '—');
        }

        if ($submission->submission_type === $typeStockOut) {
            $clientName = $clientNames[(int) ($submission->payload['client_id'] ?? 0)] ?? 'Client';
            $releaseDate = $submission->payload['release_date'] ?? null;
            $release = $releaseDate ? \Illuminate\Support\Carbon::parse($releaseDate)->format('M j, Y') : '—';

            return 'Client: '.$clientName.' | Release: '.$release;
        }

        if ($submission->submission_type === $typeStockIn) {
            $suppliers = collect([
                $supplierNames[(int) ($submission->payload['seedling']['supplier_id'] ?? 0)] ?? null,
                $supplierNames[(int) ($submission->payload['scion']['supplier_id'] ?? 0)] ?? null,
            ])->filter()->unique()->values();

            return 'Supplier: '.($suppliers->isEmpty() ? '—' : $suppliers->implode(' + '));
        }

        if ($submission->submission_type === $typeBatchCreation) {
            $variety = $varietyNames[(int) ($submission->payload['scion_variety_id'] ?? 0)] ?? 'Scion variety';

            if (($submission->payload['source'] ?? null) === \App\Models\Batch::SOURCE_SUPPLIER) {
                $supplier = $supplierNames[(int) ($submission->payload['supplier_id'] ?? 0)] ?? 'Supplier';
                $date = $submission->payload['delivery_date'] ?? null;

                return 'Supplier: '.$supplier.' | Variety: '.$variety.' | Delivery: '.($date ?: '—');
            }

            return 'Variety: '.$variety.' | Grafted: '.($submission->payload['grafted_date'] ?? '—');
        }

        return '';
    };

    $emptyTitle = $hasFilters
        ? 'No matching submissions'
        : match ($statusFilter) {
            \App\Models\OperationalSubmission::STATUS_APPROVED => 'No approved submissions',
            \App\Models\OperationalSubmission::STATUS_REJECTED => 'No rejected submissions',
            default => 'No pending submissions',
        };

    $emptyText = $hasFilters
        ? 'Try a different search or clear the filter.'
        : match ($statusFilter) {
            \App\Models\OperationalSubmission::STATUS_APPROVED => 'Approved submissions will appear here.',
            \App\Models\OperationalSubmission::STATUS_REJECTED => 'Rejected submissions will appear here.',
            default => "You're all caught up!",
        };
@endphp

<div class="submission-page queue-page">
    <nav class="submission-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" class="submission-breadcrumb-home" aria-label="Dashboard">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span>Operational</span>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span class="active">Submission Queue</span>
    </nav>

    <section class="queue-hero" aria-labelledby="queuePageTitle">
        <svg class="queue-hero-art" viewBox="0 0 320 190" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M256 18c-48 4-85 30-100 68-6 15-6 30 1 41 29-7 56-23 74-46 17-21 25-42 25-63Z" fill="#8dc26a" opacity=".62"/>
            <path d="M173 95c-20 10-34 27-40 49-2 10 0 20 5 28 18-5 34-16 44-32 9-15 13-31 11-47-7 0-13 1-20 3Z" fill="#4f8f3e" opacity=".42"/>
            <ellipse cx="255" cy="118" rx="17" ry="34" transform="rotate(24 255 118)" fill="#c98b2f" opacity=".82"/>
            <ellipse cx="255" cy="118" rx="23" ry="43" transform="rotate(24 255 118)" stroke="#a86c1c" stroke-width="2" opacity=".55"/>
            <ellipse cx="255" cy="118" rx="9" ry="36" transform="rotate(24 255 118)" stroke="#a86c1c" stroke-width="1.4" opacity=".45"/>
            <path d="M290 38c9 15 13 32 11 50" stroke="#6ca84d" stroke-width="2" stroke-linecap="round" opacity=".45"/>
        </svg>

        <span class="queue-hero-icon"><i class="bi bi-clipboard2-check"></i></span>

        <div class="queue-hero-copy">
            <h1 id="queuePageTitle">Submission Queue</h1>
            <p>Review and decide on Staff operational submissions.</p>
        </div>
    </section>

    <div class="queue-toolbar">
        <div class="queue-tabs" role="tablist" aria-label="Submission status">
            @foreach ($statusLabels as $status => $label)
                <a href="{{ route('operational.submissions.queue', $tabParams($status)) }}"
                    class="queue-tab {{ $statusFilter === $status ? 'active' : '' }}"
                    role="tab" aria-selected="{{ $statusFilter === $status ? 'true' : 'false' }}">
                    <i class="bi {{ $statusIcons[$status] }}"></i>
                    {{ $label }}
                    @if (($statusCounts[$status] ?? 0) > 0)
                        <span class="queue-tab-count">{{ $statusCounts[$status] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('operational.submissions.queue') }}" class="queue-toolbar-search" role="search">
            <input type="hidden" name="status" value="{{ $statusFilter }}">

            <div class="input-group submission-search">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ $search }}" class="form-control"
                    placeholder="Search by subject or batch code..." aria-label="Search submissions">
            </div>

            <button class="btn btn-outline-secondary submission-filter-toggle" type="button"
                data-bs-toggle="collapse" data-bs-target="#queueFilters"
                aria-expanded="{{ $activeTypeFilter ? 'true' : 'false' }}" aria-controls="queueFilters">
                <i class="bi bi-funnel"></i> Filter
                @if ($activeTypeFilter)
                    <span class="badge rounded-pill text-bg-success ms-1">1</span>
                @endif
            </button>

            <div class="collapse {{ $activeTypeFilter ? 'show' : '' }} submission-filter-panel" id="queueFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-8">
                        <label class="form-label small text-muted mb-1" for="queueTypeFilter">Type</label>
                        <select id="queueTypeFilter" name="type" class="form-select form-select-sm">
                            <option value="">All types</option>
                            <option value="mortality" @selected($typeFilter === 'mortality')>Mortality</option>
                            <option value="stock_out" @selected($typeFilter === 'stock_out')>Stock Out</option>
                            <option value="stock_in" @selected($typeFilter === 'stock_in')>Stock In</option>
                            @if ($batchCreationEnabled)
                                <option value="batch_creation" @selected($typeFilter === 'batch_creation')>Batch Creation</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-sm-4 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-success flex-fill">Apply</button>
                        <a href="{{ route('operational.submissions.queue', ['status' => $statusFilter]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="queue-list">
        @forelse ($submissions as $submission)
            <article class="queue-card queue-card-{{ $submission->status }}">
                <div class="queue-card-type">
                    <span class="queue-type-icon">
                        <i class="bi {{ $typeIcons[$submission->submission_type] ?? 'bi-file-earmark-text' }}"></i>
                    </span>
                    <div class="queue-type-copy">
                        <h2>{{ $subjectLabel($submission) }}</h2>
                        <span class="status-pill {{ $statusClasses[$submission->status] ?? '' }}">
                            <i class="bi {{ $statusIcons[$submission->status] ?? 'bi-circle-fill' }}"></i>
                            {{ $statusLabels[$submission->status] ?? ucfirst($submission->status) }}
                        </span>
                    </div>
                </div>

                <div class="queue-card-batch">
                    <div class="queue-card-code">{{ $batchCodeLabel($submission) }}</div>
                    <div class="queue-card-meta">
                        <span>Submitted by <strong>{{ $submission->submitter?->name ?? 'Unknown user' }}</strong></span>
                        <span>
                            <i class="bi bi-calendar3"></i>
                            {{ $submission->submitted_at?->format('M j, Y h:i A') ?? '—' }}
                        </span>
                        @if ($submission->resubmissionOf)
                            <span class="queue-card-resubmission">
                                <i class="bi bi-arrow-repeat"></i>
                                Resubmission of #{{ $submission->resubmissionOf->submission_id }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="queue-card-details">
                    <h3>Details</h3>
                    <p>{{ $detailsPrimary($submission) }}</p>
                    <p class="queue-details-muted">{{ $detailsSecondary($submission) }}</p>
                    @if ($submission->isRejected() && $submission->decision_reason)
                        <p class="queue-details-reason">Reason: {{ $submission->decision_reason }}</p>
                    @endif
                </div>

                <div class="queue-card-action">
                    <a href="{{ route('operational.submissions.show', $submission) }}" class="queue-review-btn">
                        <i class="bi bi-eye"></i>
                        {{ $submission->isPending() ? 'Review' : 'View' }}
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </article>
        @empty
            <div class="queue-empty">
                <div class="queue-empty-art" aria-hidden="true">
                    <i class="bi bi-clipboard2-check"></i>
                    <i class="bi bi-flower2 queue-empty-leaf queue-empty-leaf-left"></i>
                    <i class="bi bi-flower2 queue-empty-leaf queue-empty-leaf-right"></i>
                </div>
                <div class="queue-empty-title">
                    <span></span>
                    <h2>{{ $emptyTitle }}</h2>
                    <span></span>
                </div>
                <p>{{ $emptyText }}</p>
                @if ($hasFilters)
                    <a href="{{ route('operational.submissions.queue', ['status' => $statusFilter]) }}" class="btn btn-sm btn-outline-secondary mt-2">
                        Clear filter
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    @if ($submissions->hasPages())
        <div class="queue-pagination">
            {{ $submissions->links() }}
        </div>
    @endif
</div>
@endsection
