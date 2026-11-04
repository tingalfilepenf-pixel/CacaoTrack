@extends('layouts.app')

@php
    $resubmissionOf = $resubmissionOf ?? null;
    $originalPayload = $resubmissionOf->payload ?? [];
    $isResubmission = $resubmissionOf !== null;
@endphp

@section('title', $isResubmission ? 'Resubmit Stock Out' : 'Submit Stock Out')
@section('page_title', $isResubmission ? 'Resubmit Stock Out' : 'Submit Stock Out')

@section('page_actions')
<a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Back to Submissions</a>
@endsection

@section('content')
@php
    $defaultDetails = ! empty($originalPayload['details'])
        ? $originalPayload['details']
        : [['batch_id' => '', 'quantity' => 1, 'unit' => $units[0] ?? 'pots', 'unit_price' => '']];
    $oldDetails = old('details', $defaultDetails);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->submission_stockout->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isResubmission ? route('operational.submissions.resubmit.stock-out', $resubmissionOf) : route('operational.submissions.stock-out.store') }}" id="submissionStockOutForm">
    @csrf
    <div class="card">
        <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Stock-out details</h6></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="submissionClient" class="form-label text-muted small fw-bold">Client <span class="text-danger">*</span></label>
                    <select class="form-select @error('client_id', 'submission_stockout') is-invalid @enderror" id="submissionClient" name="client_id" required>
                        <option value="" disabled @selected(old('client_id', $originalPayload['client_id'] ?? null) === null)>Select Client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->client_id }}" @selected(old('client_id', $originalPayload['client_id'] ?? null) == $client->client_id)>{{ $client->client_name }}</option>
                        @endforeach
                    </select>
                    @error('client_id', 'submission_stockout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="submissionReleaseDate" class="form-label text-muted small fw-bold">Release Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('release_date', 'submission_stockout') is-invalid @enderror" id="submissionReleaseDate" name="release_date" max="{{ now()->toDateString() }}" value="{{ old('release_date', $originalPayload['release_date'] ?? now()->toDateString()) }}" required>
                    @error('release_date', 'submission_stockout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label text-muted small fw-bold mb-0">Detail Lines <span class="text-danger">*</span></label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addSubmissionLine">Add Line</button>
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 220px;">BATCH</th>
                            <th style="min-width: 130px;">QUANTITY</th>
                            <th style="min-width: 130px;">UNIT</th>
                            <th style="min-width: 140px;">UNIT PRICE</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="submissionDetailLines">
                        @foreach ($oldDetails as $index => $line)
                            <tr class="detail-line">
                                <td>
                                    <select name="details[{{ $index }}][batch_id]" class="form-select form-select-sm" required>
                                        <option value="">Select batch</option>
                                        @foreach ($batches as $batch)
                                            <option value="{{ $batch->batch_id }}" @selected(($line['batch_id'] ?? '') == $batch->batch_id)>
                                                {{ $batch->batch_code }} — {{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }} (Available: {{ number_format($batch->survivingQuantity()) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error("details.{$index}.batch_id", 'submission_stockout')<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" name="details[{{ $index }}][quantity]" class="form-control form-control-sm" min="1" value="{{ $line['quantity'] ?? 1 }}" required>
                                    @error("details.{$index}.quantity", 'submission_stockout')<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <select name="details[{{ $index }}][unit]" class="form-select form-select-sm" required>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit }}" @selected(($line['unit'] ?? $units[0]) === $unit)>{{ ucfirst($unit) }}</option>
                                        @endforeach
                                    </select>
                                    @error("details.{$index}.unit", 'submission_stockout')<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" name="details[{{ $index }}][unit_price]" class="form-control form-control-sm" min="0" step="0.01" value="{{ $line['unit_price'] ?? '' }}" placeholder="0.00">
                                    @error("details.{$index}.unit_price", 'submission_stockout')<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>This entry stays Pending until a Manager verifies it. It does not reserve stock or change any official record.
            </p>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-secondary">Submit for Verification</button>
        </div>
    </div>
</form>

<template id="submissionLineTemplate">
    <tr class="detail-line">
        <td>
            <select name="details[__INDEX__][batch_id]" class="form-select form-select-sm" required>
                <option value="">Select batch</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->batch_id }}">
                        {{ $batch->batch_code }} — {{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }} (Available: {{ number_format($batch->survivingQuantity()) }})
                    </option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="details[__INDEX__][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>
        <td>
            <select name="details[__INDEX__][unit]" class="form-select form-select-sm" required>
                @foreach ($units as $unit)
                    <option value="{{ $unit }}">{{ ucfirst($unit) }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="details[__INDEX__][unit_price]" class="form-control form-control-sm" min="0" step="0.01" placeholder="0.00"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line">Remove</button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const body = document.getElementById('submissionDetailLines');
        const template = document.getElementById('submissionLineTemplate');
        let nextIndex = {{ count($oldDetails) }};

        document.getElementById('addSubmissionLine').addEventListener('click', function () {
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, nextIndex++).trim();
            body.appendChild(wrapper.firstElementChild);
        });

        body.addEventListener('click', function (event) {
            const remove = event.target.closest('.remove-line');
            if (!remove) {
                return;
            }

            if (body.querySelectorAll('.detail-line').length === 1) {
                return;
            }

            remove.closest('.detail-line').remove();
        });
    });
</script>
@endpush
