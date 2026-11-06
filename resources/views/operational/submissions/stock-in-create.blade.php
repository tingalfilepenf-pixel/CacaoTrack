@extends('layouts.app')

@php
    $resubmissionOf = $resubmissionOf ?? null;
    $originalPayload = $resubmissionOf->payload ?? [];
    $isResubmission = $resubmissionOf !== null;
    $formAction = $isResubmission
        ? route('operational.submissions.resubmit.stock-in', $resubmissionOf)
        : route('operational.submissions.stock-in.store');

    $stockInErrorKeys = collect($errors->submission_stockin->keys());
    $seedlingHasErrors = $stockInErrorKeys->contains(fn ($key) => str_starts_with($key, 'seedling.'));
    $scionHasErrors = $stockInErrorKeys->contains(fn ($key) => str_starts_with($key, 'scion.'));

    $seedlingOld = (array) old('seedling', $originalPayload['seedling'] ?? []);
    $scionOld = (array) old('scion', $originalPayload['scion'] ?? []);
    $seedlingFilled = collect(['supplier_id', 'quantity', 'origin', 'notes'])
        ->contains(fn ($key) => filled($seedlingOld[$key] ?? null));
    $scionFilled = collect(['supplier_id', 'quantity', 'origin', 'scion_variety_id', 'unit_price', 'notes'])
        ->contains(fn ($key) => filled($scionOld[$key] ?? null));

    $activeEntry = $seedlingHasErrors
        ? 'seedling'
        : ($scionHasErrors
            ? 'scion'
            : ($scionFilled && ! $seedlingFilled ? 'scion' : 'seedling'));
@endphp

@section('title', $isResubmission ? 'Resubmit Stock In' : 'Submit Stock In')
@section('page_title', $isResubmission ? 'Resubmit Stock In' : 'Submit Stock In')

@section('page_actions')
<a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Back to Submissions</a>
@endsection

@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->submission_stockin->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="stockInForm" method="POST" action="{{ $formAction }}">
    @csrf
    <div class="card">
        <div class="card-header bg-white py-3"><h6 class="m-0 font-weight-bold text-primary">Stock-in details</h6></div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="stockInTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeEntry === 'seedling' ? 'active' : '' }}" id="seedlingsTab" data-bs-toggle="tab"
                        data-bs-target="#seedlingsPane" type="button" role="tab"
                        aria-controls="seedlingsPane" aria-selected="{{ $activeEntry === 'seedling' ? 'true' : 'false' }}">
                        <i class="bi bi-tree me-1"></i> Seedlings
                        <span class="badge rounded-pill bg-success ms-1 {{ $seedlingFilled ? '' : 'd-none' }}" data-tab-filled="seedling">Filled</span>
                        <span class="badge rounded-pill bg-danger ms-1 {{ $seedlingHasErrors ? '' : 'd-none' }}" data-tab-error="seedling" title="This tab needs attention">!</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeEntry === 'scion' ? 'active' : '' }}" id="scionTab" data-bs-toggle="tab"
                        data-bs-target="#scionPane" type="button" role="tab"
                        aria-controls="scionPane" aria-selected="{{ $activeEntry === 'scion' ? 'true' : 'false' }}">
                        <i class="bi bi-flower1 me-1"></i> Scion
                        <span class="badge rounded-pill bg-success ms-1 {{ $scionFilled ? '' : 'd-none' }}" data-tab-filled="scion">Filled</span>
                        <span class="badge rounded-pill bg-danger ms-1 {{ $scionHasErrors ? '' : 'd-none' }}" data-tab-error="scion" title="This tab needs attention">!</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade {{ $activeEntry === 'seedling' ? 'show active' : '' }}" id="seedlingsPane" role="tabpanel" aria-labelledby="seedlingsTab">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <div class="fw-bold text-success"><i class="bi bi-tree me-1"></i>Seedlings entry</div>
                        <div class="text-muted small">Required when you fill in this tab.</div>
                    </div>
                    <div class="alert alert-light border small py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Seedlings are recorded without a variety or price.
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-label text-muted small fw-bold" for="seedlingSupplier">Supplier <span class="text-danger">*</span></label>
                            @if (auth()->user()->hasPermission('manage_inventory'))
                                <button type="button" class="btn btn-sm btn-link p-0 js-add-supplier" data-supplier-target="seedling"
                                    data-bs-toggle="modal" data-bs-target="#supplierModal">+ Add Supplier</button>
                            @endif
                        </div>
                        <select class="form-select @error('seedling.supplier_id', 'submission_stockin') is-invalid @enderror" id="seedlingSupplier" name="seedling[supplier_id]">
                            <option value="" @selected((string) old('seedling.supplier_id', '') === '')>Select Supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}" @selected((string) old('seedling.supplier_id') === (string) $supplier->supplier_id)>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('seedling.supplier_id', 'submission_stockin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @unless (auth()->user()->hasPermission('manage_inventory'))
                            <div class="form-text">Not listed? Ask a Manager to add it.</div>
                        @endunless
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="seedlingQuantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('seedling.quantity', 'submission_stockin') is-invalid @enderror" id="seedlingQuantity"
                                name="seedling[quantity]" min="1" step="1" inputmode="numeric" placeholder="e.g. 500" value="{{ old('seedling.quantity', $originalPayload['seedling']['quantity'] ?? '') }}">
                            @error('seedling.quantity', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Whole units received.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="seedlingOrigin">Origin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('seedling.origin', 'submission_stockin') is-invalid @enderror" id="seedlingOrigin"
                                name="seedling[origin]" maxlength="255" placeholder="e.g. Batangas" value="{{ old('seedling.origin', $originalPayload['seedling']['origin'] ?? '') }}">
                            @error('seedling.origin', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="seedlingDate">Date Received <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('seedling.date_received', 'submission_stockin') is-invalid @enderror" id="seedlingDate"
                                name="seedling[date_received]" max="{{ now()->toDateString() }}" value="{{ old('seedling.date_received', $originalPayload['seedling']['date_received'] ?? now()->toDateString()) }}">
                            @error('seedling.date_received', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="seedlingNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control @error('seedling.notes', 'submission_stockin') is-invalid @enderror" id="seedlingNotes" name="seedling[notes]"
                            rows="2" maxlength="1000" placeholder="e.g. Premium bundle">{{ old('seedling.notes', $originalPayload['seedling']['notes'] ?? '') }}</textarea>
                        @error('seedling.notes', 'submission_stockin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <button type="button" class="btn btn-link btn-sm p-0" data-clear-entry="seedling">Clear this entry</button>
                        <button type="button" class="btn btn-link btn-sm p-0" data-copy-entry="seedling">Copy supplier and date from the other tab</button>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeEntry === 'scion' ? 'show active' : '' }}" id="scionPane" role="tabpanel" aria-labelledby="scionTab">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <div class="fw-bold text-info-emphasis"><i class="bi bi-flower1 me-1"></i>Scion entry</div>
                        <div class="text-muted small">The supplier is optional; the other fields are required when you fill in this tab.</div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-label text-muted small fw-bold" for="scionSupplier">Supplier <span class="text-muted fw-normal">(optional)</span></label>
                            @if (auth()->user()->hasPermission('manage_inventory'))
                                <button type="button" class="btn btn-sm btn-link p-0 js-add-supplier" data-supplier-target="scion"
                                    data-bs-toggle="modal" data-bs-target="#supplierModal">+ Add Supplier</button>
                            @endif
                        </div>
                        <select class="form-select @error('scion.supplier_id', 'submission_stockin') is-invalid @enderror" id="scionSupplier" name="scion[supplier_id]">
                            <option value="" @selected((string) old('scion.supplier_id', '') === '')>Select Supplier (optional)</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}" @selected((string) old('scion.supplier_id') === (string) $supplier->supplier_id)>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('scion.supplier_id', 'submission_stockin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @unless (auth()->user()->hasPermission('manage_inventory'))
                            <div class="form-text">Not listed? Ask a Manager to add it.</div>
                        @endunless
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="scionQuantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('scion.quantity', 'submission_stockin') is-invalid @enderror" id="scionQuantity"
                                name="scion[quantity]" min="1" step="1" inputmode="numeric" placeholder="e.g. 300" value="{{ old('scion.quantity', $originalPayload['scion']['quantity'] ?? '') }}">
                            @error('scion.quantity', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Whole units received.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="scionOrigin">Origin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('scion.origin', 'submission_stockin') is-invalid @enderror" id="scionOrigin"
                                name="scion[origin]" maxlength="255" placeholder="e.g. Batangas" value="{{ old('scion.origin', $originalPayload['scion']['origin'] ?? '') }}">
                            @error('scion.origin', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold" for="scionDate">Date Received <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('scion.date_received', 'submission_stockin') is-invalid @enderror" id="scionDate"
                                name="scion[date_received]" max="{{ now()->toDateString() }}" value="{{ old('scion.date_received', $originalPayload['scion']['date_received'] ?? now()->toDateString()) }}">
                            @error('scion.date_received', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="scionVariety">Scion Variety <span class="text-danger">*</span></label>
                            <select class="form-select @error('scion.scion_variety_id', 'submission_stockin') is-invalid @enderror" id="scionVariety" name="scion[scion_variety_id]">
                                <option value="" @selected((string) old('scion.scion_variety_id', '') === '')>Select a scion variety...</option>
                                @foreach ($scionVarieties as $scionVariety)
                                    <option value="{{ $scionVariety->scion_variety_id }}" @selected((string) old('scion.scion_variety_id') === (string) $scionVariety->scion_variety_id)>
                                        {{ $scionVariety->scion_variety_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('scion.scion_variety_id', 'submission_stockin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="scionUnitPrice">Unit Price <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control @error('scion.unit_price', 'submission_stockin') is-invalid @enderror" id="scionUnitPrice"
                                    name="scion[unit_price]" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 12.50" value="{{ old('scion.unit_price', $originalPayload['scion']['unit_price'] ?? '') }}">
                                @error('scion.unit_price', 'submission_stockin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">Price per scion; enter 0 if there is no charge.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center rounded border bg-light px-3 py-2">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Calculated Amount</div>
                                <div class="form-text mt-0">Quantity &times; Unit Price (display only)</div>
                            </div>
                            <span class="fs-4 fw-bold text-primary" id="stockInCalculatedAmount" aria-live="polite">₱0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="scionNotes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control @error('scion.notes', 'submission_stockin') is-invalid @enderror" id="scionNotes" name="scion[notes]"
                            rows="2" maxlength="1000" placeholder="e.g. Premium bundle">{{ old('scion.notes', $originalPayload['scion']['notes'] ?? '') }}</textarea>
                        @error('scion.notes', 'submission_stockin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <button type="button" class="btn btn-link btn-sm p-0" data-clear-entry="scion">Clear this entry</button>
                        <button type="button" class="btn btn-link btn-sm p-0" data-copy-entry="scion">Copy supplier and date from the other tab</button>
                    </div>
                </div>
            </div>

            <p class="text-muted small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>This entry stays Pending until a Manager verifies it. It does not create an official stock-in record or change any balance.
            </p>
        </div>
        <div class="border-top bg-light px-3 py-2" id="stockInSaveSummaryBar">
            <div class="small text-muted" id="stockInSaveSummary" aria-live="polite">Fill in at least one tab.</div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('operational.submissions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" id="stockInSubmitBtn">Submit for Verification</button>
        </div>
    </div>
</form>

@if (auth()->user()->hasPermission('manage_inventory'))
    <div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="supplierForm" method="POST" action="{{ route('inventory.suppliers.store') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="stock_in_submission">
                    @if ($resubmissionOf)
                        <input type="hidden" name="resubmission_of" value="{{ $resubmissionOf->submission_id }}">
                    @endif
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="supplierName">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('supplier_name', 'supplier') is-invalid @enderror" id="supplierName"
                                name="supplier_name" maxlength="255" required value="{{ old('supplier_name') }}">
                            @error('supplier_name', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="supplierContact">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('contact_number', 'supplier') is-invalid @enderror" id="supplierContact"
                                name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                            @error('contact_number', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" for="supplierAddress">Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('address', 'supplier') is-invalid @enderror" id="supplierAddress"
                                name="address" maxlength="255" required value="{{ old('address') }}">
                            @error('address', 'supplier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="supplierSubmitBtn">Save Supplier</button>
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
        const defaultStockInDate = @json(now()->toDateString());
        const fieldIds = {
            seedling: {
                supplier_id: 'seedlingSupplier',
                quantity: 'seedlingQuantity',
                origin: 'seedlingOrigin',
                date_received: 'seedlingDate',
                notes: 'seedlingNotes',
            },
            scion: {
                supplier_id: 'scionSupplier',
                quantity: 'scionQuantity',
                origin: 'scionOrigin',
                date_received: 'scionDate',
                scion_variety_id: 'scionVariety',
                unit_price: 'scionUnitPrice',
                notes: 'scionNotes',
            },
        };
        const dataFieldKeys = {
            seedling: ['supplier_id', 'quantity', 'origin', 'notes'],
            scion: ['supplier_id', 'quantity', 'origin', 'scion_variety_id', 'unit_price', 'notes'],
        };
        const summaryEl = document.getElementById('stockInSaveSummary');
        const calculatedAmountEl = document.getElementById('stockInCalculatedAmount');

        const getField = function (tab, key) {
            return document.getElementById(fieldIds[tab][key]);
        };

        const hasEntryData = function (tab) {
            return (dataFieldKeys[tab] || []).some(function (key) {
                const el = getField(tab, key);

                return el ? String(el.value || '').trim() !== '' : false;
            });
        };

        const entryQuantity = function (tab) {
            const el = getField(tab, 'quantity');
            const value = el ? Number(el.value) : NaN;

            return el && el.value !== '' && Number.isFinite(value) && value > 0
                ? value.toLocaleString()
                : null;
        };

        const formatPeso = function (value) {
            return '₱' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const refreshCalculatedAmount = function () {
            if (!calculatedAmountEl) {
                return;
            }

            const quantityInput = getField('scion', 'quantity');
            const unitPriceInput = getField('scion', 'unit_price');
            const quantity = quantityInput ? Number(quantityInput.value) : NaN;
            const unitPrice = unitPriceInput ? Number(unitPriceInput.value) : NaN;
            const hasQuantity = quantityInput && quantityInput.value !== '' && Number.isFinite(quantity) && quantity >= 0;
            const hasUnitPrice = unitPriceInput && unitPriceInput.value !== '' && Number.isFinite(unitPrice) && unitPrice >= 0;

            calculatedAmountEl.textContent = hasQuantity && hasUnitPrice
                ? formatPeso(quantity * unitPrice)
                : '₱0.00';
        };

        const refreshEntryHelpers = function () {
            Object.keys(dataFieldKeys).forEach(function (tab) {
                const filled = hasEntryData(tab);
                document.querySelectorAll('[data-tab-filled="' + tab + '"]').forEach(function (mark) {
                    mark.classList.toggle('d-none', !filled);
                });
            });

            const parts = [];
            ['seedling', 'scion'].forEach(function (tab) {
                if (!hasEntryData(tab)) {
                    return;
                }

                const label = tab === 'seedling' ? 'Seedlings' : 'Scion';
                const quantity = entryQuantity(tab);
                parts.push(quantity ? label + ' ' + quantity : label);
            });

            if (summaryEl) {
                summaryEl.textContent = parts.length === 0
                    ? 'Fill in at least one tab.'
                    : 'Will submit: ' + parts.join(' and ');
            }

            refreshCalculatedAmount();
        };

        const clearPane = function (tab) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el) {
                    el.value = key === 'date_received' ? defaultStockInDate : '';
                }
            });
        };

        const snapshotPane = function (tab) {
            const snapshot = {};
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                snapshot[key] = el ? el.value : '';
            });

            return snapshot;
        };

        const restorePane = function (tab, values) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el && typeof values[key] === 'string') {
                    el.value = values[key];
                }
            });
        };

        Object.keys(fieldIds).forEach(function (tab) {
            Object.keys(fieldIds[tab]).forEach(function (key) {
                const el = getField(tab, key);
                if (el) {
                    el.addEventListener('input', refreshEntryHelpers);
                    el.addEventListener('change', refreshEntryHelpers);
                }
            });
        });

        document.querySelectorAll('[data-clear-entry]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearPane(button.getAttribute('data-clear-entry'));
                refreshEntryHelpers();
            });
        });

        document.querySelectorAll('[data-copy-entry]').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.getAttribute('data-copy-entry');
                const source = target === 'seedling' ? 'scion' : 'seedling';
                const targetSupplier = getField(target, 'supplier_id');
                const sourceSupplier = getField(source, 'supplier_id');
                const targetDate = getField(target, 'date_received');
                const sourceDate = getField(source, 'date_received');

                if (targetSupplier && sourceSupplier) {
                    targetSupplier.value = sourceSupplier.value;
                }
                if (targetDate && sourceDate) {
                    targetDate.value = sourceDate.value;
                }

                refreshEntryHelpers();
            });
        });

        const stockInDraftKey = 'cacaotrack.stockInSubmissionDraft';
        let supplierTargetPane = null;

        document.querySelectorAll('.js-add-supplier').forEach(function (button) {
            button.addEventListener('click', function () {
                supplierTargetPane = button.getAttribute('data-supplier-target') || null;
            });
        });

        const supplierForm = document.getElementById('supplierForm');
        if (supplierForm) {
            supplierForm.addEventListener('submit', function () {
                try {
                    sessionStorage.setItem(stockInDraftKey, JSON.stringify({
                        target: supplierTargetPane,
                        seedling: snapshotPane('seedling'),
                        scion: snapshotPane('scion'),
                    }));
                } catch (error) {
                    // Browser storage unavailable: the server still validates.
                }
            });
        }

        const newSupplierId = @json(session('new_supplier_id'));
        if (newSupplierId) {
            let draft = null;

            try {
                draft = JSON.parse(sessionStorage.getItem(stockInDraftKey) || 'null');
            } catch (error) {
                draft = null;
            }

            if (draft) {
                restorePane('seedling', draft.seedling || {});
                restorePane('scion', draft.scion || {});

                if (draft.target && fieldIds[draft.target]) {
                    const supplierSelect = getField(draft.target, 'supplier_id');
                    if (supplierSelect) {
                        supplierSelect.value = String(newSupplierId);
                    }
                }
            }

            try {
                sessionStorage.removeItem(stockInDraftKey);
            } catch (error) {
                // Nothing to clean up.
            }
        }

        if (supplierForm) {
            const supplierModalEl = document.getElementById('supplierModal');
            if (supplierModalEl && @json($errors->supplier->any())) {
                new bootstrap.Modal(supplierModalEl).show();
            }
        }

        const stockInForm = document.getElementById('stockInForm');
        if (stockInForm) {
            stockInForm.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && event.target
                    && (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT')) {
                    event.preventDefault();
                }
            });
        }

        const guardSubmit = function (formId, buttonId, label) {
            const form = document.getElementById(formId);
            const button = document.getElementById(buttonId);

            if (!form || !button) {
                return;
            }

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + label;
            });
        };

        guardSubmit('stockInForm', 'stockInSubmitBtn', 'Submitting…');
        guardSubmit('supplierForm', 'supplierSubmitBtn', 'Saving…');

        refreshEntryHelpers();
    });
</script>
@endpush
