@extends('layouts.app')

@section('title', 'Submission #'.$submission->submission_id)
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

    $backRoute = $canVerify
        ? route('operational.submissions.queue')
        : route('operational.submissions.index');
    $backLabel = $canVerify ? 'Back to Queue' : 'Back to My Submissions';

    $batchCode = '—';

    if ($submission->submission_type === $typeMortality) {
        $batchCode = $submission->subjectBatch?->batch_code
            ?? ($batchCodes[(int) ($submission->subject_batch_id ?? ($submission->payload['batch_id'] ?? 0))] ?? '—');
    } elseif ($submission->submission_type === $typeStockOut) {
        $codes = collect($submission->payload['details'] ?? [])
            ->pluck('batch_id')
            ->map(fn ($id) => $batchCodes[(int) $id] ?? null)
            ->filter()
            ->unique()
            ->values();

        $batchCode = $codes->isEmpty() ? '—' : $codes->implode(', ');
    } elseif ($submission->submission_type === $typeBatchCreation) {
        $batchCode = $officialBatchCodes[$submission->submission_id] ?? 'Assigned at approval';
    }

    $batchLabel = $submission->submission_type === $typeStockOut ? 'Batches' : 'Batch';

    $submittedDate = $submission->submitted_at?->format('M j, Y');
    $submittedTime = $submission->submitted_at?->format('h:i A');

    $canDecide = $canVerify
        && $submission->isPending()
        && (int) $submission->submitted_by_id !== (int) auth()->id();

    $canResubmit = $canSubmit
        && $submission->isRejected()
        && (int) $submission->submitted_by_id === (int) auth()->id()
        && ($submission->submission_type !== $typeStockIn || $stockInSubmissionEnabled)
        && ($submission->submission_type !== $typeBatchCreation || $batchCreationEnabled);

    $totalStockOutQuantity = $submission->submission_type === $typeStockOut
        ? collect($submission->payload['details'] ?? [])->sum('quantity')
        : 0;
@endphp

<div class="submission-page submission-detail-page">
    <nav class="submission-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" class="submission-breadcrumb-home" aria-label="Dashboard">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <a href="{{ $backRoute }}" class="submission-breadcrumb-link">Operational</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span class="active">{{ $canVerify ? 'Submission Queue' : 'Operational Submissions' }}</span>
    </nav>

    <section class="queue-hero detail-hero" aria-labelledby="detailPageTitle">
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
            <h1 id="detailPageTitle">Submission #{{ $submission->submission_id }}</h1>
            <p>{{ $canDecide ? 'Review and decide on this staff submission.' : 'View the details and decision for this submission.' }}</p>
        </div>

        <a href="{{ $backRoute }}" class="submission-back-btn">
            <i class="bi bi-arrow-left"></i> {{ $backLabel }}
        </a>
    </section>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->submission_rejection->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->submission_rejection->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="detail-card" aria-labelledby="submissionDetailsTitle">
        <div class="detail-card-header">
            <i class="bi bi-info-circle-fill"></i>
            <h2 id="submissionDetailsTitle">Submission Details</h2>
        </div>
        <div class="detail-card-body">
            <div class="detail-info-grid">
                <div class="detail-type">
                    <span class="detail-type-icon">
                        <i class="bi {{ $typeIcons[$submission->submission_type] ?? 'bi-file-earmark-text' }}"></i>
                    </span>
                    <div>
                        <span class="detail-type-chip">{{ $submission->typeLabel() }}</span>
                        <div class="detail-batch-line">{{ $batchLabel }}: {{ $batchCode }}</div>
                    </div>
                </div>

                <div class="detail-field">
                    <span class="detail-field-icon"><i class="bi bi-person"></i></span>
                    <div>
                        <div class="detail-field-label">Submitted By</div>
                        <div class="detail-field-value">{{ $submission->submitter?->name ?? 'Unknown user' }}</div>
                    </div>
                </div>

                <div class="detail-field">
                    <span class="detail-field-icon"><i class="bi bi-calendar3"></i></span>
                    <div>
                        <div class="detail-field-label">Submitted At</div>
                        <div class="detail-field-value">
                            {{ $submittedDate ?? '—' }}@if ($submittedTime) · {{ $submittedTime }}@endif
                        </div>
                    </div>
                </div>

                <div class="detail-status">
                    <span class="status-pill {{ $statusClasses[$submission->status] ?? '' }}">
                        <i class="bi {{ $statusIcons[$submission->status] ?? 'bi-circle-fill' }}"></i>
                        {{ $statusLabels[$submission->status] ?? ucfirst($submission->status) }}
                    </span>
                </div>
            </div>

            @if ($submission->resubmissionOf)
                <div class="detail-resubmission-note">
                    <i class="bi bi-arrow-repeat"></i>
                    Resubmission of
                    <a href="{{ route('operational.submissions.show', $submission->resubmissionOf) }}">
                        #{{ $submission->resubmissionOf->submission_id }}
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="detail-card" aria-labelledby="frozenRequestTitle">
        <div class="detail-card-header">
            <i class="bi bi-snow"></i>
            <h2 id="frozenRequestTitle">Frozen Request</h2>
        </div>
        <div class="detail-card-body">
            @if ($submission->submission_type === $typeMortality)
                <div class="frozen-grid">
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-box"></i></span>
                        <div>
                            <div class="frozen-label">Quantity</div>
                            <div class="frozen-value">{{ number_format((int) ($submission->payload['quantity'] ?? 0)) }}</div>
                        </div>
                    </div>
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-lightning-charge"></i></span>
                        <div>
                            <div class="frozen-label">Cause</div>
                            <div class="frozen-value">
                                {{ $submission->payload['cause_other'] ?: ($submission->payload['cause'] ?? '—') }}
                            </div>
                        </div>
                    </div>
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-calendar3"></i></span>
                        <div>
                            <div class="frozen-label">Recorded Date</div>
                            <div class="frozen-value">{{ $submission->payload['recorded_at'] ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            @elseif ($submission->submission_type === $typeStockOut)
                <div class="frozen-grid">
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-person"></i></span>
                        <div>
                            <div class="frozen-label">Client</div>
                            <div class="frozen-value">
                                {{ $clientNames[(int) ($submission->payload['client_id'] ?? 0)] ?? 'Client' }}
                            </div>
                        </div>
                    </div>
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-calendar3"></i></span>
                        <div>
                            <div class="frozen-label">Release Date</div>
                            <div class="frozen-value">{{ $submission->payload['release_date'] ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="frozen-item">
                        <span class="frozen-icon"><i class="bi bi-box-seam"></i></span>
                        <div>
                            <div class="frozen-label">Total Quantity</div>
                            <div class="frozen-value">{{ number_format((int) $totalStockOutQuantity) }}</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive detail-lines-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th class="text-end">Quantity</th>
                                <th>Unit</th>
                                <th class="text-end">Unit Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($submission->payload['details'] ?? []) as $line)
                                <tr>
                                    <td>{{ $batchCodes[(int) ($line['batch_id'] ?? 0)] ?? '#'.($line['batch_id'] ?? '—') }}</td>
                                    <td class="text-end">{{ number_format((int) ($line['quantity'] ?? 0)) }}</td>
                                    <td>{{ $line['unit'] ?? '—' }}</td>
                                    <td class="text-end">
                                        {{ isset($line['unit_price']) ? '₱'.number_format((float) $line['unit_price'], 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($submission->submission_type === $typeStockIn)
                @foreach (['seedling' => 'Seedlings entry', 'scion' => 'Scion entry'] as $tab => $label)
                    @php
                        $entry = $submission->payload[$tab] ?? null;
                        $supplierName = is_array($entry) && ! empty($entry['supplier_id'])
                            ? ($stockInSuppliers[(int) $entry['supplier_id']] ?? '#'.$entry['supplier_id'])
                            : '—';
                        $varietyName = is_array($entry) && ! empty($entry['scion_variety_id'])
                            ? ($stockInVarieties[(int) $entry['scion_variety_id']] ?? '#'.$entry['scion_variety_id'])
                            : '—';
                    @endphp

                    @if (is_array($entry))
                        <div class="frozen-entry">
                            <h3 class="frozen-entry-title">{{ $label }}</h3>
                            <div class="frozen-grid">
                                <div class="frozen-item">
                                    <span class="frozen-icon"><i class="bi bi-truck"></i></span>
                                    <div>
                                        <div class="frozen-label">Supplier</div>
                                        <div class="frozen-value">{{ $supplierName }}</div>
                                    </div>
                                </div>
                                <div class="frozen-item">
                                    <span class="frozen-icon"><i class="bi bi-box"></i></span>
                                    <div>
                                        <div class="frozen-label">Quantity</div>
                                        <div class="frozen-value">{{ number_format((int) ($entry['quantity'] ?? 0)) }}</div>
                                    </div>
                                </div>
                                <div class="frozen-item">
                                    <span class="frozen-icon"><i class="bi bi-geo-alt"></i></span>
                                    <div>
                                        <div class="frozen-label">Origin</div>
                                        <div class="frozen-value">{{ $entry['origin'] ?? '—' }}</div>
                                    </div>
                                </div>
                                @if ($tab === 'scion')
                                    <div class="frozen-item">
                                        <span class="frozen-icon"><i class="bi bi-flower2"></i></span>
                                        <div>
                                            <div class="frozen-label">Scion Variety</div>
                                            <div class="frozen-value">{{ $varietyName }}</div>
                                        </div>
                                    </div>
                                    <div class="frozen-item">
                                        <span class="frozen-icon"><i class="bi bi-cash-coin"></i></span>
                                        <div>
                                            <div class="frozen-label">Unit Price</div>
                                            <div class="frozen-value">
                                                {{ isset($entry['unit_price']) ? '₱'.number_format((float) $entry['unit_price'], 2) : '—' }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div class="frozen-item">
                                    <span class="frozen-icon"><i class="bi bi-calendar3"></i></span>
                                    <div>
                                        <div class="frozen-label">Date Received</div>
                                        <div class="frozen-value">{{ $entry['date_received'] ?? '—' }}</div>
                                    </div>
                                </div>
                                <div class="frozen-item">
                                    <span class="frozen-icon"><i class="bi bi-chat-left-text"></i></span>
                                    <div>
                                        <div class="frozen-label">Notes</div>
                                        <div class="frozen-value">{{ $entry['notes'] ?: '—' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @else
                @php
                    $batchSource = $submission->payload['source'] ?? \App\Models\Batch::SOURCE_OWN;
                    $batchVariety = $varietyNames[(int) ($submission->payload['scion_variety_id'] ?? 0)] ?? 'Scion variety';
                    $batchSupplier = $supplierNames[(int) ($submission->payload['supplier_id'] ?? 0)] ?? 'Supplier';
                @endphp

                <div class="frozen-entry">
                    <h3 class="frozen-entry-title">
                        {{ $batchSource === \App\Models\Batch::SOURCE_SUPPLIER ? 'Supplier delivery' : 'Own nursery batch' }}
                    </h3>
                    <div class="frozen-grid">
                        <div class="frozen-item">
                            <span class="frozen-icon"><i class="bi bi-flower2"></i></span>
                            <div>
                                <div class="frozen-label">Scion Variety</div>
                                <div class="frozen-value">{{ $batchVariety }}</div>
                            </div>
                        </div>

                        @if ($batchSource === \App\Models\Batch::SOURCE_SUPPLIER)
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-truck"></i></span>
                                <div>
                                    <div class="frozen-label">Supplier</div>
                                    <div class="frozen-value">{{ $batchSupplier }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-calendar3"></i></span>
                                <div>
                                    <div class="frozen-label">Delivery Date</div>
                                    <div class="frozen-value">{{ $submission->payload['delivery_date'] ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-box-seam"></i></span>
                                <div>
                                    <div class="frozen-label">Plants Delivered</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['plants_delivered'] ?? 0)) }}</div>
                                </div>
                            </div>
                        @else
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-tree"></i></span>
                                <div>
                                    <div class="frozen-label">Seedlings Used</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['seedlings_used'] ?? 0)) }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-flower1"></i></span>
                                <div>
                                    <div class="frozen-label">Scion Used</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['scion_used'] ?? 0)) }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-box-seam"></i></span>
                                <div>
                                    <div class="frozen-label">Plants Grafted</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['plants_grafted'] ?? 0)) }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-calendar3"></i></span>
                                <div>
                                    <div class="frozen-label">Grafted Date</div>
                                    <div class="frozen-value">{{ $submission->payload['grafted_date'] ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-boxes"></i></span>
                                <div>
                                    <div class="frozen-label">Seedlings Available (at submission)</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['seedlings_available'] ?? 0)) }}</div>
                                </div>
                            </div>
                            <div class="frozen-item">
                                <span class="frozen-icon"><i class="bi bi-flower2"></i></span>
                                <div>
                                    <div class="frozen-label">Scion Available (at submission)</div>
                                    <div class="frozen-value">{{ number_format((int) ($submission->payload['scion_available'] ?? 0)) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($batchSource === \App\Models\Batch::SOURCE_OWN && is_array($batchBalances))
                    <div class="alert alert-light border small mt-3 mb-0">
                        <strong>Balances now:</strong>
                        {{ number_format((int) $batchBalances['seedlings']) }} seedlings available,
                        {{ number_format((int) ($batchBalances['scion'] ?? 0)) }} {{ $batchVariety }} scion available.
                    </div>
                @endif
            @endif
        </div>
    </section>

    <section class="detail-card" aria-labelledby="decisionTitle">
        <div class="detail-card-header">
            <i class="bi bi-check-circle-fill"></i>
            <h2 id="decisionTitle">Decision</h2>
        </div>
        <div class="detail-card-body">
            @if ($submission->decided_at)
                <div class="decision-summary">
                    <div class="decision-summary-item">
                        <div class="detail-field-label">Status</div>
                        <div class="detail-field-value">
                            <span class="status-pill {{ $statusClasses[$submission->status] ?? '' }}">
                                <i class="bi {{ $statusIcons[$submission->status] ?? 'bi-circle-fill' }}"></i>
                                {{ $statusLabels[$submission->status] ?? ucfirst($submission->status) }}
                            </span>
                        </div>
                    </div>
                    <div class="decision-summary-item">
                        <div class="detail-field-label">Decided By</div>
                        <div class="detail-field-value">{{ $submission->decider?->name ?? 'Unknown user' }}</div>
                    </div>
                    <div class="decision-summary-item">
                        <div class="detail-field-label">Decided At</div>
                        <div class="detail-field-value">{{ $submission->decided_at->format('M j, Y · h:i A') }}</div>
                    </div>
                    <div class="decision-summary-item">
                        <div class="detail-field-label">Decision Type</div>
                        <div class="detail-field-value">{{ $submission->decision_automatic ? 'Automatic rejection' : 'Manager decision' }}</div>
                    </div>
                    <div class="decision-summary-item decision-summary-wide">
                        <div class="detail-field-label">Reason</div>
                        <div class="detail-field-value">{{ $submission->decision_reason ?: '—' }}</div>
                    </div>
                </div>

                @if ($canResubmit)
                    <div class="decision-actions">
                        <a href="{{ route('operational.submissions.resubmit.create', $submission) }}" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-repeat"></i> Resubmit this request
                        </a>
                    </div>
                @endif

                @if ($submission->records->isNotEmpty())
                    <div class="official-records">
                        <h3>Official records created</h3>
                        <ul>
                            @foreach ($submission->records as $record)
                                <li>
                                    @if ($record->record_type === \App\Models\OperationalSubmissionRecord::RECORD_TYPE_BATCH)
                                        Batch {{ $officialBatchCodes[$submission->submission_id] ?? '#'.$record->record_id }}
                                    @else
                                        {{ $record->record_type }} #{{ $record->record_id }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @else
                <div class="decision-note">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>This submission is waiting for Manager verification.</span>
                </div>

                <div class="decision-actions">
                    <a href="{{ $backRoute }}" class="btn btn-light">Cancel</a>

                    @if ($canDecide)
                        <form method="POST" action="{{ route('operational.submissions.approve', $submission) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle"></i> Approve Submission
                            </button>
                        </form>

                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectSubmissionModal">
                            <i class="bi bi-x-circle"></i> Reject Submission
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>

@if ($canDecide)
    <div class="modal fade" id="rejectSubmissionModal" tabindex="-1" aria-labelledby="rejectSubmissionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content submission-reject-modal">
                <form method="POST" action="{{ route('operational.submissions.reject', $submission) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectSubmissionModalLabel">Reject Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Give a written reason. The submitter will see it in their submission history.
                        </p>
                        <label for="rejectionReason" class="form-label small fw-bold">
                            Rejection reason <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control @error('reason', 'submission_rejection') is-invalid @enderror"
                            id="rejectionReason" name="reason" rows="4" required>{{ old('reason') }}</textarea>
                        @error('reason', 'submission_rejection')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Submission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    @if ($errors->submission_rejection->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.getElementById('rejectSubmissionModal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
@endsection
