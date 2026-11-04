@extends('layouts.app')

@php
    $resubmissionOf = $resubmissionOf ?? null;
    $original = $resubmissionOf->payload ?? [];
    $isResubmission = $resubmissionOf !== null;
@endphp

@section('title', $isResubmission ? 'Resubmit Mortality' : 'Submit Mortality')
@section('page_title', $isResubmission ? 'Resubmit Mortality' : 'Submit Mortality')

@section('page_actions')
<a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Back to Submissions</a>
@endsection

@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->submission_mortality->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isResubmission ? route('operational.submissions.resubmit.mortality', $resubmissionOf) : route('operational.submissions.mortality.store') }}">
    @csrf
    <div class="card">
        <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Mortality details</h6></div>
        <div class="card-body">
            <div class="mb-3">
                <label for="submissionBatch" class="form-label text-muted small fw-bold">Batch <span class="text-danger">*</span></label>
                <select class="form-select @error('batch_id', 'submission_mortality') is-invalid @enderror" id="submissionBatch" name="batch_id" required>
                    <option value="" disabled @selected(old('batch_id', request('batch_id', $original['batch_id'] ?? null)) === null)>Select an active batch</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(old('batch_id', request('batch_id', $original['batch_id'] ?? null)) == $batch->batch_id)>
                            {{ $batch->batch_code }} ({{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}) — Available: {{ number_format($batch->survivingQuantity()) }}
                        </option>
                    @endforeach
                </select>
                @error('batch_id', 'submission_mortality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="submissionQuantity" class="form-label text-muted small fw-bold">Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('quantity', 'submission_mortality') is-invalid @enderror" id="submissionQuantity" name="quantity" min="1" value="{{ old('quantity', $original['quantity'] ?? '') }}" required>
                    @error('quantity', 'submission_mortality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="submissionDate" class="form-label text-muted small fw-bold">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('recorded_at', 'submission_mortality') is-invalid @enderror" id="submissionDate" name="recorded_at" max="{{ now()->toDateString() }}" value="{{ old('recorded_at', $original['recorded_at'] ?? now()->toDateString()) }}" required>
                    @error('recorded_at', 'submission_mortality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label for="submissionCause" class="form-label text-muted small fw-bold">Cause of Death <span class="text-danger">*</span></label>
                <select class="form-select @error('cause', 'submission_mortality') is-invalid @enderror" id="submissionCause" name="cause" required>
                    <option value="" disabled @selected(old('cause', $original['cause'] ?? null) === null)>Select cause of death</option>
                    @foreach ($causes as $cause)
                        <option value="{{ $cause }}" @selected(old('cause', $original['cause'] ?? null) === $cause)>{{ $cause }}</option>
                    @endforeach
                </select>
                @error('cause', 'submission_mortality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 d-none" id="submissionCauseOtherWrapper">
                <label for="submissionCauseOther" class="form-label text-muted small fw-bold">Description <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('cause_other', 'submission_mortality') is-invalid @enderror" id="submissionCauseOther" name="cause_other" maxlength="200" value="{{ old('cause_other', $original['cause_other'] ?? '') }}" placeholder="Specify the cause of death">
                @error('cause_other', 'submission_mortality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i>This entry stays Pending until a Manager verifies it. It does not change any official record or balance.
            </p>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-danger">Submit for Verification</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const cause = document.getElementById('submissionCause');
        const wrapper = document.getElementById('submissionCauseOtherWrapper');
        const other = document.getElementById('submissionCauseOther');
        const others = @json(\App\Models\MortalityRecord::CAUSE_OTHERS);

        const toggle = function () {
            const isOthers = cause.value === others;
            wrapper.classList.toggle('d-none', !isOthers);
            other.required = isOthers;
        };

        cause.addEventListener('change', toggle);
        toggle();
    });
</script>
@endpush
