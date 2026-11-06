@extends('layouts.app')

@section('title', 'Operational Submissions')
@section('page_title', '')
@section('page_actions', '')

@section('content')
@php
    $typeMortality = \App\Models\OperationalSubmission::TYPE_MORTALITY;
    $typeStockOut = \App\Models\OperationalSubmission::TYPE_STOCK_OUT;
    $typeStockIn = \App\Models\OperationalSubmission::TYPE_STOCK_IN;

    $typeClasses = [
        $typeMortality => 'type-mortality',
        $typeStockOut => 'type-stock-out',
        $typeStockIn => 'type-stock-in',
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

    $activeFilterCount = ($typeFilter !== '' ? 1 : 0) + ($statusFilter !== '' ? 1 : 0);

    $subjectLabel = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeStockIn, $clientNames, $supplierNames): string {
        if ($submission->submission_type === $typeMortality) {
            return 'Mortality Report';
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

        return $submission->typeLabel();
    };

    $subjectMeta = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $typeStockIn): string {
        if ($submission->submission_type === $typeMortality) {
            $quantity = (int) ($submission->payload['quantity'] ?? 0);
            $cause = $submission->payload['cause'] ?? null;

            return number_format($quantity).' '.($quantity === 1 ? 'plant' : 'plants').($cause ? ' · '.$cause : '');
        }

        if ($submission->submission_type === $typeStockOut) {
            $lineCount = count($submission->payload['details'] ?? []);
            $releaseDate = $submission->payload['release_date'] ?? null;
            $release = $releaseDate ? \Illuminate\Support\Carbon::parse($releaseDate)->format('M j, Y') : null;

            return $lineCount.' '.($lineCount === 1 ? 'line' : 'lines').($release ? ' · Release '.$release : '');
        }

        if ($submission->submission_type === $typeStockIn) {
            return $submission->stockInSummary();
        }

        return '';
    };

    $batchCodeLabel = function (\App\Models\OperationalSubmission $submission) use ($typeMortality, $typeStockOut, $batchCodes): string {
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

            if ($codes->count() > 2) {
                return $codes->take(2)->implode(', ').' +'.($codes->count() - 2);
            }

            return $codes->implode(', ');
        }

        return '—';
    };
@endphp

<div class="submission-page">
    <nav class="submission-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" class="submission-breadcrumb-home" aria-label="Dashboard">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span>Operational</span>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span class="active">Operational Submissions</span>
    </nav>

    <section class="submission-hero" aria-labelledby="submissionPageTitle">
        <svg class="submission-hero-art" viewBox="0 0 260 220" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M212 24c-55 3-101 37-118 83-7 19-6 37 3 51 34-8 66-28 88-56 20-26 28-52 27-78Z" fill="currentColor"/>
            <path d="M61 96c-18 9-31 26-35 47-2 10 0 20 5 27 16-5 30-16 39-30 9-15 12-31 9-47-6 0-12 1-18 3Z" fill="currentColor" opacity=".7"/>
            <path d="M154 17c-2 22-9 42-21 60" stroke="rgba(255,255,255,.35)" stroke-width="2" stroke-linecap="round"/>
        </svg>

        <div class="submission-hero-content">
            <div class="submission-hero-copy">
                <h1 id="submissionPageTitle">Operational Submissions</h1>
                <p>Submit and track your operational transactions. A Manager will review and approve or reject them.</p>
            </div>

            @if ($canSubmit)
                <div class="submission-hero-actions">
                    <a href="{{ route('operational.submissions.mortality.create') }}" class="btn btn-danger">
                        <i class="bi bi-plus-lg"></i> Submit Mortality
                    </a>
                    <a href="{{ route('operational.submissions.stock-out.create') }}" class="btn btn-dark">
                        <i class="bi bi-box-arrow-right"></i> Submit Stock Out
                    </a>
                    @if ($stockInSubmissionEnabled)
                        <a href="{{ route('operational.submissions.stock-in.create') }}" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Submit Stock In
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="submission-workflow" aria-labelledby="submissionWorkflowTitle">
        <div class="workflow-intro">
            <span class="workflow-intro-icon"><i class="bi bi-info-circle-fill"></i></span>
            <div>
                <h2 id="submissionWorkflowTitle">Submission Workflow</h2>
                <p>Staff submits, Manager reviews.</p>
            </div>
        </div>

        <div class="workflow-steps">
            @foreach ([
                ['title' => 'Staff Submits', 'text' => 'Your submission is saved as Pending.'],
                ['title' => 'Manager Reviews', 'text' => 'The Manager checks the details.'],
                ['title' => 'Approve or Reject', 'text' => 'Approval creates the official record. Rejection shows a reason.'],
                ['title' => 'Check the Result', 'text' => 'View the decision in your submission history.'],
            ] as $step)
                <div class="workflow-step">
                    <span class="workflow-step-number">{{ $loop->iteration }}</span>
                    <div>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['text'] }}</p>
                    </div>
                </div>
                @if (! $loop->last)
                    <i class="bi bi-arrow-right workflow-arrow" aria-hidden="true"></i>
                @endif
            @endforeach
        </div>
    </section>

    <div class="card submission-card">
        <div class="submission-card-header">
            <div class="d-flex align-items-center gap-2">
                <span class="submission-title-icon"><i class="bi bi-inbox-fill"></i></span>
                <h2>{{ $canViewAll ? 'All Submissions' : 'My Submissions' }}</h2>
            </div>

            <form method="GET" action="{{ route('operational.submissions.index') }}" class="submission-toolbar" role="search">
                <div class="input-group submission-search">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $search }}" class="form-control"
                        placeholder="Search by subject or batch..." aria-label="Search submissions">
                </div>

                <button class="btn btn-outline-secondary submission-filter-toggle" type="button"
                    data-bs-toggle="collapse" data-bs-target="#submissionFilters"
                    aria-expanded="{{ $hasFilters ? 'true' : 'false' }}" aria-controls="submissionFilters">
                    <i class="bi bi-funnel"></i> Filter
                    @if ($activeFilterCount > 0)
                        <span class="badge rounded-pill text-bg-success ms-1">{{ $activeFilterCount }}</span>
                    @endif
                </button>

                <div class="collapse {{ $hasFilters ? 'show' : '' }} submission-filter-panel" id="submissionFilters">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5">
                            <label class="form-label small text-muted mb-1" for="submissionTypeFilter">Type</label>
                            <select id="submissionTypeFilter" name="type" class="form-select form-select-sm">
                                <option value="">All types</option>
                                <option value="mortality" @selected($typeFilter === 'mortality')>Mortality</option>
                                <option value="stock_out" @selected($typeFilter === 'stock_out')>Stock Out</option>
                                @if ($stockInSubmissionEnabled)
                                    <option value="stock_in" @selected($typeFilter === 'stock_in')>Stock In</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small text-muted mb-1" for="submissionStatusFilter">Status</label>
                            <select id="submissionStatusFilter" name="status" class="form-select form-select-sm">
                                <option value="">All statuses</option>
                                <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
                                <option value="approved" @selected($statusFilter === 'approved')>Approved</option>
                                <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
                            </select>
                        </div>
                        <div class="col-sm-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-success flex-fill">Apply</button>
                            <a href="{{ route('operational.submissions.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive submission-table-wrap">
            <table class="table submission-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Type</th>
                        <th>Subject</th>
                        <th>Batch Code</th>
                        <th>Submitted By</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Decision</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td class="ps-4">
                                <span class="type-pill {{ $typeClasses[$submission->submission_type] ?? '' }}">
                                    {{ $submission->typeLabel() }}
                                </span>
                            </td>
                            <td>
                                <div class="submission-subject">{{ $subjectLabel($submission) }}</div>
                                <div class="submission-subject-meta">{{ $subjectMeta($submission) }}</div>
                                @if ($submission->resubmissionOf)
                                    <div class="submission-resubmission">
                                        <i class="bi bi-arrow-repeat"></i>
                                        Resubmission of #{{ $submission->resubmissionOf->submission_id }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="batch-code">{{ $batchCodeLabel($submission) }}</span></td>
                            <td><div class="submission-submitter">{{ $submission->submitter?->name ?? 'Unknown user' }}</div></td>
                            <td>
                                <div class="submission-date">{{ $submission->submitted_at?->format('M j, Y') ?? '—' }}</div>
                                @if ($submission->submitted_at)
                                    <div class="submission-time">{{ $submission->submitted_at->format('h:i A') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="status-pill {{ $statusClasses[$submission->status] ?? '' }}">
                                    <i class="bi {{ $statusIcons[$submission->status] ?? 'bi-circle-fill' }}"></i>
                                    {{ ucfirst($submission->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($submission->decided_at)
                                    @if ($submission->isApproved())
                                        <div class="decision-approved">Approved by {{ $submission->decider?->name ?? 'Unknown user' }}</div>
                                        <div class="decision-date">({{ $submission->decided_at->format('M j, Y') }})</div>
                                    @else
                                        <div class="decision-reason">{{ $submission->decision_reason ?: 'Rejected' }}</div>
                                        <div class="decision-date">
                                            {{ $submission->decider?->name ?? 'Unknown user' }} · {{ $submission->decided_at->format('M j, Y') }}
                                            @if ($submission->decision_automatic)
                                                <span class="decision-auto">Automatic</span>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('operational.submissions.show', $submission) }}" class="btn btn-sm btn-primary submission-view-btn">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="submission-empty">
                                    <div class="submission-empty-icon">
                                        <i class="bi bi-file-earmark-text"></i>
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    @if ($hasFilters)
                                        <h3>No matching submissions</h3>
                                        <p>No submissions match your search or filters. Try a different term or clear the filters.</p>
                                        <a href="{{ route('operational.submissions.index') }}" class="btn btn-sm btn-outline-secondary mt-2">
                                            Clear filters
                                        </a>
                                    @else
                                        <h3>No submissions yet</h3>
                                        <p>Your Mortality, Stock Out, and Stock In submissions will appear here after you submit them.</p>
                                        <p class="mb-0">Choose an action above to get started.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($submissions->hasPages())
            <div class="submission-pagination">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
