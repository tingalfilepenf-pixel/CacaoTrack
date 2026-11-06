@extends('layouts.app')

@php
    $resubmissionOf = $resubmissionOf ?? null;
    $originalPayload = $resubmissionOf->payload ?? [];
    $isResubmission = $resubmissionOf !== null;
    $formAction = $isResubmission
        ? route('operational.submissions.resubmit.batch-creation', $resubmissionOf)
        : route('operational.submissions.batch-creation.store');

    $selectedSource = old('source', $originalPayload['source'] ?? \App\Models\Batch::SOURCE_OWN);
    $ownSource = \App\Models\Batch::SOURCE_OWN;
    $supplierSource = \App\Models\Batch::SOURCE_SUPPLIER;

    $varietyAvailability = collect($receivedScionVarieties)
        ->merge($activeScionVarieties)
        ->unique('scion_variety_id')
        ->mapWithKeys(fn ($variety) => [
            (int) $variety->scion_variety_id => $variety->availableScionQuantity(),
        ]);

    $seedlingsUsed = old('seedlings_used', $originalPayload['seedlings_used'] ?? '');
    $scionVarietyId = old('scion_variety_id', $originalPayload['scion_variety_id'] ?? '');
    $scionUsed = old('scion_used', $originalPayload['scion_used'] ?? '');
    $plantsGrafted = old('plants_grafted', $originalPayload['plants_grafted'] ?? '');
    $supplierId = old('supplier_id', $originalPayload['supplier_id'] ?? '');
    $deliveryDate = old('delivery_date', $originalPayload['delivery_date'] ?? now()->toDateString());
    $plantsDelivered = old('plants_delivered', $originalPayload['plants_delivered'] ?? '');
@endphp

@section('title', $isResubmission ? 'Resubmit Batch Creation' : 'Submit Batch Creation')
@section('page_title', $isResubmission ? 'Resubmit Batch Creation' : 'Submit Batch Creation')

@section('page_actions')
<a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Back to Submissions</a>
@endsection

@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->submission_batch->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="batchCreationForm" method="POST" action="{{ $formAction }}">
    @csrf

    <div class="card">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Batch source</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="card border rounded-3 p-3 h-100 w-100 {{ $selectedSource === $ownSource ? 'border-success bg-success-subtle' : '' }}"
                        for="sourceOwn" style="cursor: pointer;">
                        <div class="d-flex gap-2">
                            <input class="form-check-input mt-1" type="radio" name="source" id="sourceOwn"
                                value="{{ $ownSource }}" @checked($selectedSource === $ownSource) required>
                            <div>
                                <div class="fw-bold"><i class="bi bi-tree me-1 text-success"></i>Own nursery</div>
                                <div class="text-muted small">Use seedlings and scion already received in Stock In.</div>
                            </div>
                        </div>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="card border rounded-3 p-3 h-100 w-100 {{ $selectedSource === $supplierSource ? 'border-success bg-success-subtle' : '' }}"
                        for="sourceSupplier" style="cursor: pointer;">
                        <div class="d-flex gap-2">
                            <input class="form-check-input mt-1" type="radio" name="source" id="sourceSupplier"
                                value="{{ $supplierSource }}" @checked($selectedSource === $supplierSource) required>
                            <div>
                                <div class="fw-bold"><i class="bi bi-truck me-1 text-primary"></i>Supplier delivery</div>
                                <div class="text-muted small">Record grafted plants received from an external supplier.</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 d-none" id="ownNurseryPanel" data-batch-source-panel="{{ $ownSource }}">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Own nursery details</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-light border small py-2">
                <i class="bi bi-info-circle me-1"></i>
                Only the quantities you use are deducted, and only after a Manager approves this request.
                Stock is not held while the request is Pending.
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold" for="seedlingsAvailable">Seedlings available</label>
                    <input type="text" class="form-control" id="seedlingsAvailable" value="{{ number_format($seedlingsAvailable) }}" disabled readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold" for="seedlingsUsed">Seedlings used <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('seedlings_used', 'submission_batch') is-invalid @enderror"
                        id="seedlingsUsed" name="seedlings_used" min="1" step="1" inputmode="numeric"
                        max="{{ $seedlingsAvailable }}" value="{{ $seedlingsUsed }}">
                    @error('seedlings_used', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Whole seedlings taken from the seedling stock pool.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold" for="ownScionUsed">Scion used <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('scion_used', 'submission_batch') is-invalid @enderror"
                        id="ownScionUsed" name="scion_used" min="1" step="1" inputmode="numeric"
                        value="{{ $scionUsed }}">
                    @error('scion_used', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text" id="ownScionAvailableText">Choose a scion variety to see its available stock.</div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold" for="ownScionVariety">Scion variety <span class="text-danger">*</span></label>
                    <select class="form-select @error('scion_variety_id', 'submission_batch') is-invalid @enderror"
                        id="ownScionVariety" name="scion_variety_id">
                        <option value="">Choose a scion variety</option>
                        @foreach ($receivedScionVarieties as $variety)
                            <option value="{{ $variety->scion_variety_id }}"
                                data-available="{{ $variety->availableScionQuantity() }}"
                                @selected((string) $scionVarietyId === (string) $variety->scion_variety_id)>
                                {{ $variety->scion_variety_name }} ({{ number_format($variety->availableScionQuantity()) }} available)
                            </option>
                        @endforeach
                    </select>
                    @error('scion_variety_id', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Only active varieties recorded in a scion Stock In are listed.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold" for="plantsGrafted">Plants grafted <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('plants_grafted', 'submission_batch') is-invalid @enderror"
                        id="plantsGrafted" name="plants_grafted" min="1" step="1" inputmode="numeric"
                        value="{{ $plantsGrafted }}">
                    @error('plants_grafted', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Defaults to the smaller of seedlings used and scion used; you may enter less.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 d-none" id="supplierDeliveryPanel" data-batch-source-panel="{{ $supplierSource }}">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Supplier delivery details</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-light border small py-2">
                <i class="bi bi-info-circle me-1"></i>
                A supplier delivery does not deduct seedling or scion stock and is not linked to Stock In.
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between">
                        <label class="form-label text-muted small fw-bold" for="batchSupplier">Supplier <span class="text-danger">*</span></label>
                        @if (auth()->user()->hasPermission('manage_inventory'))
                            <button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#batchSupplierModal">
                                + Add Supplier
                            </button>
                        @endif
                    </div>
                    <select class="form-select @error('supplier_id', 'submission_batch') is-invalid @enderror"
                        id="batchSupplier" name="supplier_id">
                        <option value="">Choose a supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->supplier_id }}" @selected((string) $supplierId === (string) $supplier->supplier_id)>
                                {{ $supplier->supplier_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @unless (auth()->user()->hasPermission('manage_inventory'))
                        <div class="form-text">Not listed? Ask a Manager to add it.</div>
                    @endunless
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold" for="deliveryDate">Delivery date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('delivery_date', 'submission_batch') is-invalid @enderror"
                        id="deliveryDate" name="delivery_date" max="{{ now()->toDateString() }}" value="{{ $deliveryDate }}">
                    @error('delivery_date', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold" for="supplierScionVariety">Scion variety <span class="text-danger">*</span></label>
                    <select class="form-select @error('scion_variety_id', 'submission_batch') is-invalid @enderror"
                        id="supplierScionVariety" name="scion_variety_id">
                        <option value="">Choose a scion variety</option>
                        @foreach ($activeScionVarieties as $variety)
                            <option value="{{ $variety->scion_variety_id }}"
                                data-available="{{ $variety->availableScionQuantity() }}"
                                @selected((string) $scionVarietyId === (string) $variety->scion_variety_id)>
                                {{ $variety->scion_variety_name }} ({{ number_format($variety->availableScionQuantity()) }} available)
                            </option>
                        @endforeach
                    </select>
                    @error('scion_variety_id', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text" id="supplierScionAvailableText">The delivery does not deduct this stock.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold" for="plantsDelivered">Plants delivered <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('plants_delivered', 'submission_batch') is-invalid @enderror"
                        id="plantsDelivered" name="plants_delivered" min="1" step="1" inputmode="numeric"
                        value="{{ $plantsDelivered }}">
                    @error('plants_delivered', 'submission_batch')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('operational.submissions.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-success" id="batchCreationSubmitBtn">
            <i class="bi bi-send"></i> Submit for verification
        </button>
    </div>
</form>

@if (auth()->user()->hasPermission('manage_inventory'))
    <div class="modal fade" id="batchSupplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="batchSupplierForm" method="POST" action="{{ route('inventory.suppliers.store') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="batch_creation_submission">
                    @if ($resubmissionOf)
                        <input type="hidden" name="resubmission_of" value="{{ $resubmissionOf->submission_id }}">
                    @endif
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="batchSupplierName">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('supplier_name', 'supplier') is-invalid @enderror"
                                id="batchSupplierName" name="supplier_name" maxlength="255" required value="{{ old('supplier_name') }}">
                            @error('supplier_name', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="batchSupplierContact">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('contact_number', 'supplier') is-invalid @enderror"
                                id="batchSupplierContact" name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                            @error('contact_number', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="batchSupplierAddress">Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('address', 'supplier') is-invalid @enderror"
                                id="batchSupplierAddress" name="address" maxlength="255" required value="{{ old('address') }}">
                            @error('address', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="batchSupplierSubmitBtn">Save Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const panels = document.querySelectorAll('[data-batch-source-panel]');
        const sourceInputs = document.querySelectorAll('input[name="source"]');
        const seedlingsAvailable = {{ (int) $seedlingsAvailable }};
        const seedlingsUsed = document.getElementById('seedlingsUsed');
        const ownScionVariety = document.getElementById('ownScionVariety');
        const ownScionUsed = document.getElementById('ownScionUsed');
        const ownScionAvailableText = document.getElementById('ownScionAvailableText');
        const plantsGrafted = document.getElementById('plantsGrafted');
        const supplierScionVariety = document.getElementById('supplierScionVariety');
        const plantsDelivered = document.getElementById('plantsDelivered');
        const form = document.getElementById('batchCreationForm');
        const submitButton = document.getElementById('batchCreationSubmitBtn');
        let plantsTouched = plantsGrafted && plantsGrafted.value !== '';

        const varietyAvailability = @json($varietyAvailability);

        const syncPanels = function () {
            const selected = document.querySelector('input[name="source"]:checked');
            const source = selected ? selected.value : '{{ $ownSource }}';

            panels.forEach(function (panel) {
                const active = panel.getAttribute('data-batch-source-panel') === source;
                panel.classList.toggle('d-none', !active);
                panel.querySelectorAll('input, select').forEach(function (field) {
                    field.disabled = !active;
                });
            });

            // The source radios are always enabled.
            sourceInputs.forEach(function (input) { input.disabled = false; });
        };

        const availableForVariety = function (select) {
            if (!select || !select.value) {
                return 0;
            }

            return Number(varietyAvailability[select.value] || 0);
        };

        const syncOwnAvailability = function () {
            const available = availableForVariety(ownScionVariety);

            if (ownScionAvailableText) {
                ownScionAvailableText.textContent = ownScionVariety && ownScionVariety.value
                    ? available + ' scion available for this variety.'
                    : 'Choose a scion variety to see its available stock.';
            }

            if (ownScionUsed) {
                ownScionUsed.max = available > 0 ? available : '';
            }

            syncPlantsDefault();
        };

        const syncPlantsDefault = function () {
            if (!plantsGrafted) {
                return;
            }

            const seedlings = Number(seedlingsUsed && seedlingsUsed.value ? seedlingsUsed.value : 0);
            const scion = Number(ownScionUsed && ownScionUsed.value ? ownScionUsed.value : 0);
            const smaller = Math.min(seedlings, scion);

            if ((!plantsTouched || !plantsGrafted.value) && smaller > 0) {
                plantsGrafted.value = smaller;
            }

            plantsGrafted.max = smaller > 0 ? smaller : '';
        };

        sourceInputs.forEach(function (input) {
            input.addEventListener('change', syncPanels);
        });
        syncPanels();

        if (seedlingsUsed) {
            seedlingsUsed.max = seedlingsAvailable > 0 ? seedlingsAvailable : '';
            seedlingsUsed.addEventListener('input', syncPlantsDefault);
        }

        if (ownScionVariety) {
            ownScionVariety.addEventListener('change', syncOwnAvailability);
        }

        if (ownScionUsed) {
            ownScionUsed.addEventListener('input', syncPlantsDefault);
        }

        if (plantsGrafted) {
            plantsGrafted.addEventListener('input', function () {
                plantsTouched = true;
            });
        }

        syncOwnAvailability();

        if (supplierScionVariety) {
            supplierScionVariety.addEventListener('change', function () {
                const available = availableForVariety(supplierScionVariety);
                const text = document.getElementById('supplierScionAvailableText');
                if (text) {
                    text.textContent = supplierScionVariety.value
                        ? available + ' scion available for this variety.'
                        : '';
                }
            });
        }

        if (plantsDelivered) {
            plantsDelivered.max = '';
        }

        const newSupplierId = @json(session('new_supplier_id'));

        if (newSupplierId) {
            const select = document.getElementById('batchSupplier');
            if (select) {
                const option = new Option('New supplier', newSupplierId, true, true);
                select.add(option);
                const sourceSupplier = document.getElementById('sourceSupplier');
                if (sourceSupplier && !sourceSupplier.checked) {
                    sourceSupplier.checked = true;
                    syncPanels();
                }
            }
        }

        const supplierModalEl = document.getElementById('batchSupplierModal');

        if (supplierModalEl && @json($errors->supplier->any())) {
            new bootstrap.Modal(supplierModalEl).show();
        }

        if (form && submitButton) {
            form.addEventListener('submit', function () {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...';
            });
        }
    });
</script>
@endpush
