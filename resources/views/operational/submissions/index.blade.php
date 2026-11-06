@extends('layouts.app')

@section('title', 'Operational Submissions')
@section('page_title', 'Operational Submissions')

@section('page_actions')
<div class="d-flex gap-2">
    @if ($canSubmit)
        <a href="{{ route('operational.submissions.mortality.create') }}" class="btn btn-danger">
            <i class="bi bi-plus-lg me-1"></i> Submit Mortality
        </a>
        <a href="{{ route('operational.submissions.stock-out.create') }}" class="btn btn-secondary">
            <i class="bi bi-box-arrow-right me-1"></i> Submit Stock Out
        </a>
        @if ($stockInSubmissionEnabled)
            <a href="{{ route('operational.submissions.stock-in.create') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i> Submit Stock In
            </a>
        @endif
    @endif
</div>
@endsection

@section('content')
@php
    $statusBadge = fn (string $status) => match ($status) {
        \App\Models\OperationalSubmission::STATUS_APPROVED => 'bg-success',
        \App\Models\OperationalSubmission::STATUS_REJECTED => 'bg-danger',
        default => 'bg-warning text-dark',
    };
    $typeLabel = fn (string $type) => match ($type) {
        \App\Models\OperationalSubmission::TYPE_MORTALITY => 'Mortality',
        \App\Models\OperationalSubmission::TYPE_STOCK_OUT => 'Stock Out',
        \App\Models\OperationalSubmission::TYPE_STOCK_IN => 'Stock In',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
@endphp

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

<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            {{ $canViewAll ? 'All submissions' : 'My submissions' }}
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">TYPE</th>
                        <th>SUBJECT</th>
                        <th>SUBMITTED BY</th>
                        <th>SUBMITTED</th>
                        <th>STATUS</th>
                        <th>DECISION</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td class="ps-4 fw-medium">{{ $typeLabel($submission->submission_type) }}</td>
                            <td>
                                @if ($submission->submission_type === \App\Models\OperationalSubmission::TYPE_MORTALITY)
                                    {{ $submission->subjectBatch?->batch_code ?? 'Batch #'.($submission->payload['batch_id'] ?? '—') }}
                                    <span class="text-muted small">({{ number_format((int) ($submission->payload['quantity'] ?? 0)) }})</span>
                                @elseif ($submission->submission_type === \App\Models\OperationalSubmission::TYPE_STOCK_OUT)
                                    Client #{{ $submission->payload['client_id'] ?? '—' }}
                                    <span class="text-muted small">({{ count($submission->payload['details'] ?? []) }} line(s))</span>
                                @else
                                    {{ $submission->stockInSummary() }}
                                @endif
                            </td>
                            <td class="text-muted small">{{ $submission->submitter?->name ?? 'Unknown user' }}</td>
                            <td class="text-muted small">{{ $submission->submitted_at?->format('M j, Y H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $statusBadge($submission->status) }}">{{ ucfirst($submission->status) }}</span></td>
                            <td class="small">
                                @if ($submission->decided_at)
                                    {{ $submission->decider?->name ?? 'Unknown user' }}
                                    <div class="text-muted">{{ $submission->decided_at->format('M j, Y H:i') }}</div>
                                    @if ($submission->decision_reason)
                                        <div class="text-danger">{{ $submission->decision_reason }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">Awaiting review</span>
                                @endif
                                @if ($submission->resubmissionOf)
                                    <div class="text-muted">Resubmission of #{{ $submission->resubmissionOf->submission_id }}</div>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('operational.submissions.show', $submission) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No submissions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($submissions->hasPages())
        <div class="card-footer bg-white">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
