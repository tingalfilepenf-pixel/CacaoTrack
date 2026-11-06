@extends('layouts.app')

@section('title', 'Submission Queue')
@section('page_title', 'Submission Queue')

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Pending submissions</h6>
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
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td class="ps-4 fw-medium">{{ $submission->typeLabel() }}</td>
                            <td>
                                @if ($submission->submission_type === \App\Models\OperationalSubmission::TYPE_MORTALITY)
                                    {{ $submission->subjectBatch?->batch_code ?? 'Batch #'.($submission->payload['batch_id'] ?? '—') }}
                                @elseif ($submission->submission_type === \App\Models\OperationalSubmission::TYPE_STOCK_OUT)
                                    Client #{{ $submission->payload['client_id'] ?? '—' }}
                                @else
                                    {{ $submission->stockInSummary() }}
                                @endif
                            </td>
                            <td class="text-muted small">{{ $submission->submitter?->name ?? 'Unknown user' }}</td>
                            <td class="text-muted small">{{ $submission->submitted_at?->format('M j, Y H:i') ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <a href="{{ route('operational.submissions.show', $submission) }}" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No pending submissions.</td>
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
