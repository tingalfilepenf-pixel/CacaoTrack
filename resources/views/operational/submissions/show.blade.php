@extends('layouts.app')

@section('title', 'Submission #'.$submission->submission_id)
@section('page_title', 'Submission #'.$submission->submission_id)

@section('page_actions')
@php
    $backRoute = auth()->user()?->hasPermission('verify_operations')
        ? route('operational.submissions.queue')
        : route('operational.submissions.index');
@endphp
<a href="{{ $backRoute }}" class="btn btn-outline-secondary">Back</a>
@endsection

@section('content')
@php
    $statusBadge = match ($submission->status) {
        \App\Models\OperationalSubmission::STATUS_APPROVED => 'bg-success',
        \App\Models\OperationalSubmission::STATUS_REJECTED => 'bg-danger',
        default => 'bg-warning text-dark',
    };
@endphp

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 font-weight-bold text-primary">Submission details</h6>
        <span class="badge {{ $statusBadge }}">{{ ucfirst($submission->status) }}</span>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Type</dt>
            <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $submission->submission_type)) }}</dd>
            <dt class="col-sm-3">Submitted by</dt>
            <dd class="col-sm-9">{{ $submission->submitter?->name ?? 'Unknown user' }}</dd>
            <dt class="col-sm-3">Submitted at</dt>
            <dd class="col-sm-9">{{ $submission->submitted_at?->format('M j, Y H:i') ?? '—' }}</dd>
            @if ($submission->subjectBatch)
                <dt class="col-sm-3">Batch</dt>
                <dd class="col-sm-9">{{ $submission->subjectBatch->batch_code }}</dd>
            @endif
            @if ($submission->resubmissionOf)
                <dt class="col-sm-3">Resubmission of</dt>
                <dd class="col-sm-9">
                    <a href="{{ route('operational.submissions.show', $submission->resubmissionOf) }}">#{{ $submission->resubmissionOf->submission_id }}</a>
                </dd>
            @endif
        </dl>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Frozen request</h6></div>
    <div class="card-body">
        @if ($submission->submission_type === \App\Models\OperationalSubmission::TYPE_MORTALITY)
            <dl class="row mb-0">
                <dt class="col-sm-3">Quantity</dt><dd class="col-sm-9">{{ number_format((int) ($submission->payload['quantity'] ?? 0)) }}</dd>
                <dt class="col-sm-3">Cause</dt><dd class="col-sm-9">{{ $submission->payload['cause'] ?? '—' }}@if (! empty($submission->payload['cause_other'])) — {{ $submission->payload['cause_other'] }}@endif</dd>
                <dt class="col-sm-3">Recorded date</dt><dd class="col-sm-9">{{ $submission->payload['recorded_at'] ?? '—' }}</dd>
            </dl>
        @else
            <dl class="row">
                <dt class="col-sm-3">Client</dt><dd class="col-sm-9">#{{ $submission->payload['client_id'] ?? '—' }}</dd>
                <dt class="col-sm-3">Release date</dt><dd class="col-sm-9">{{ $submission->payload['release_date'] ?? '—' }}</dd>
            </dl>
            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>BATCH</th><th class="text-end">QUANTITY</th><th>UNIT</th><th class="text-end">UNIT PRICE</th></tr></thead>
                    <tbody>
                        @foreach (($submission->payload['details'] ?? []) as $line)
                            <tr>
                                <td>#{{ $line['batch_id'] ?? '—' }}</td>
                                <td class="text-end">{{ number_format((int) ($line['quantity'] ?? 0)) }}</td>
                                <td>{{ $line['unit'] ?? '—' }}</td>
                                <td class="text-end">{{ isset($line['unit_price']) ? '₱'.number_format((float) $line['unit_price'], 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Decision</h6></div>
    <div class="card-body">
        @if ($submission->decided_at)
            <dl class="row mb-0">
                <dt class="col-sm-3">Decided by</dt><dd class="col-sm-9">{{ $submission->decider?->name ?? 'Unknown user' }}</dd>
                <dt class="col-sm-3">Decided at</dt><dd class="col-sm-9">{{ $submission->decided_at->format('M j, Y H:i') }}</dd>
                <dt class="col-sm-3">Reason</dt><dd class="col-sm-9">{{ $submission->decision_reason ?: '—' }}</dd>
                <dt class="col-sm-3">Automatic</dt><dd class="col-sm-9">{{ $submission->decision_automatic ? 'Yes' : 'No' }}</dd>
            </dl>
        @else
            <p class="text-muted mb-0">This submission is waiting for Manager verification.</p>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Official records created</h6></div>
    <div class="card-body">
        @forelse ($submission->records as $record)
            <div class="small">{{ $record->record_type }} #{{ $record->record_id }}</div>
        @empty
            <p class="text-muted mb-0">No official record has been created.</p>
        @endforelse
    </div>
</div>
@endsection
