@extends('layouts.app')

@section('title', 'Stock Out')
@section('page_title', 'Stock Out')

@section('page_actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#clientModal">
        <i class="bi bi-person-plus me-1"></i> New Client
    </button>
    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#stockOutModal">
        <i class="bi bi-box-arrow-right me-1"></i> New Stock Out
    </button>
</div>
@endsection

@section('content')
@php
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : '—';
    $formatAmount = fn ($value) => $value === null || $value === '' ? '—' : '₱'.number_format((float) $value, 2);

    // Repopulate the multi-line form after a failed submission so server-side
    // errors can be shown against the exact line/field that failed.
    $oldDetails = old('details', []);
    $oldLineErrors = [];
    foreach ($oldDetails as $i => $line) {
        $oldLineErrors[$i] = [
            'batch_id' => $errors->stockout->first("details.{$i}.batch_id"),
            'quantity' => $errors->stockout->first("details.{$i}.quantity"),
            'unit' => $errors->stockout->first("details.{$i}.unit"),
            'unit_price' => $errors->stockout->first("details.{$i}.unit_price"),
        ];
    }
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

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please correct the highlighted fields.</strong>
        <ul class="mb-0 ps-3">
            @foreach ($errors->getBags() as $bag)
                @foreach ($bag->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Quantity Released</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($totalReleased) }} <span class="fs-6 fw-normal text-muted">trees</span></div>
                <div class="text-muted small">Sum of the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-Out Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalTransactions) }}</div>
                <div class="text-muted small">Release records in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Clients Involved</div>
                <div class="fs-3 fw-bold">{{ number_format($totalClients) }}</div>
                <div class="text-muted small">Distinct clients in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Amount Released</div>
                <div class="fs-3 fw-bold">₱{{ number_format($totalAmount, 2) }}</div>
                <div class="text-muted small">Sum of recorded amounts (where provided)</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.stock-out') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Client or batch code...">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterClient" class="form-label text-muted small fw-bold mb-1">Client</label>
                <select class="form-select form-select-sm" id="filterClient" name="client_id">
                    <option value="">All Clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->client_id }}" @selected(($filters['client_id'] ?? null) == $client->client_id)>
                            {{ $client->client_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterBatch" class="form-label text-muted small fw-bold mb-1">Batch</label>
                <select class="form-select form-select-sm" id="filterBatch" name="batch_id">
                    <option value="">All Batches</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->batch_id }}" @selected(($filters['batch_id'] ?? null) == $batch->batch_id)>
                            {{ $batch->batch_code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterDateFrom" class="form-label text-muted small fw-bold mb-1">From</label>
                <input type="date" class="form-control form-control-sm" id="filterDateFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="filterDateTo" class="form-label text-muted small fw-bold mb-1">To</label>
                <input type="date" class="form-control form-control-sm" id="filterDateTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.stock-out') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stock Out Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Release History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="stockOutTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">STOCK ID</th>
                        <th>CLIENT</th>
                        <th>RELEASE DATE</th>
                        <th class="text-end">LINES</th>
                        <th class="text-end">TOTAL QUANTITY</th>
                        <th class="text-end">TOTAL AMOUNT</th>
                        <th>RECORDED BY</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOuts as $stockOut)
                        @php
                            $detailLines = $stockOut->details;
                            $totalQuantity = (int) $detailLines->sum('quantity');
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $stockOut->reference() }}</td>
                            <td>{{ $stockOut->client?->client_name ?? '—' }}</td>
                            <td>{{ $formatDate($stockOut->release_date) }}</td>
                            <td class="text-end">{{ number_format($detailLines->count()) }}</td>
                            <td class="text-end"><span class="badge bg-danger">{{ number_format($totalQuantity) }}</span></td>
                            <td class="text-end">{{ $formatAmount($stockOut->total_amount) }}</td>
                            <td class="text-muted small">{{ $stockOut->user?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#stockOutDetails{{ $stockOut->stock_out_id }}" aria-expanded="false" title="View lines">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="8" class="p-0 border-0">
                                <div class="collapse" id="stockOutDetails{{ $stockOut->stock_out_id }}">
                                    <div class="bg-light p-3">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>BATCH</th>
                                                    <th>SCION VARIETY</th>
                                                    <th class="text-end">QUANTITY</th>
                                                    <th>UNIT</th>
                                                    <th class="text-end">UNIT PRICE</th>
                                                    <th class="text-end">AMOUNT</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($detailLines as $line)
                                                    <tr>
                                                        <td>{{ $line->batch?->batch_code ?? '—' }}</td>
                                                        <td>{{ $line->scionVariety?->scion_variety_name ?? '—' }}</td>
                                                        <td class="text-end">{{ number_format((int) $line->quantity) }}</td>
                                                        <td>{{ ucfirst($line->unit) }}</td>
                                                        <td class="text-end">{{ $formatAmount($line->unit_price) }}</td>
                                                        <td class="text-end">{{ $formatAmount($line->amount) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-3">No detail lines recorded.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No stock-out transactions found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $stockOuts->firstItem() ?? 0 }} to {{ $stockOuts->lastItem() ?? 0 }} of {{ $stockOuts->total() }} entries
        </span>
        {{ $stockOuts->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- New Stock Out Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-secondary">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">New Stock Out</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stockOutForm" method="POST" action="{{ route('inventory.stock-out.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label text-muted small fw-bold mb-0" for="stockOutClient">Client <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0" id="switchToClientBtn">+ Add Client</button>
                            </div>
                            <select class="form-select @error('client_id', 'stockout') is-invalid @enderror" id="stockOutClient" name="client_id" required>
                                <option value="" disabled @selected(old('client_id') === null)>Select Client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->client_id }}" @selected(old('client_id', session('new_client_id')) == $client->client_id)>
                                        {{ $client->client_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('client_id', 'stockout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold" for="stockOutDate">Release Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('release_date', 'stockout') is-invalid @enderror" id="stockOutDate"
                                name="release_date" required max="{{ now()->toDateString() }}" value="{{ old('release_date', now()->toDateString()) }}">
                            @error('release_date', 'stockout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-muted small fw-bold mb-0">Detail Lines <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addDetailLineBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 220px;">BATCH <span class="text-danger">*</span></th>
                                    <th>SCION VARIETY</th>
                                    <th style="min-width: 130px;">QUANTITY <span class="text-danger">*</span></th>
                                    <th style="min-width: 130px;">UNIT <span class="text-danger">*</span></th>
                                    <th style="min-width: 140px;">UNIT PRICE</th>
                                    <th class="text-end">AMOUNT</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody id="detailLinesBody"></tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Scion Variety is derived from the selected batch. A Stock Out may contain multiple batches / scion varieties.
                    </div>

                    <template id="detailLineTemplate">
                        <tr class="detail-line">
                            <td>
                                <select name="details[__INDEX__][batch_id]" class="form-select form-select-sm line-batch" required>
                                    <option value="">Select batch</option>
                                    @foreach ($batches as $batch)
                                        <option value="{{ $batch->batch_id }}"
                                            data-available="{{ (int) $batch->available_count }}"
                                            data-batch-code="{{ $batch->batch_code }}"
                                            data-scion-variety="{{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }}">
                                            {{ $batch->batch_code }} — {{ $batch->scionVariety?->scion_variety_name ?? 'Not assigned' }} (Available: {{ number_format((int) $batch->available_count) }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-danger small mt-1 d-none line-batch-error"></div>
                            </td>
                            <td><span class="line-scion-variety text-muted">—</span></td>
                            <td>
                                <input type="number" name="details[__INDEX__][quantity]" class="form-control form-control-sm line-quantity" min="1" step="1" required>
                                <div class="form-text line-available-hint"></div>
                                <div class="text-danger small mt-1 d-none line-quantity-error"></div>
                            </td>
                            <td>
                                <select name="details[__INDEX__][unit]" class="form-select form-select-sm line-unit" required>
                                    @foreach (\App\Models\StockOutDetail::UNITS as $unit)
                                        <option value="{{ $unit }}">{{ ucfirst($unit) }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small mt-1 d-none line-unit-error"></div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="details[__INDEX__][unit_price]" class="form-control line-unit-price" min="0" step="0.01" placeholder="0.00">
                                </div>
                                <div class="text-danger small mt-1 d-none line-unit-price-error"></div>
                            </td>
                            <td class="text-end line-amount">—</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary" id="stockOutSubmitBtn">Save Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clientForm" method="POST" action="{{ route('inventory.clients.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientName">Client Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('client_name', 'client') is-invalid @enderror" id="clientName"
                            name="client_name" maxlength="255" required value="{{ old('client_name') }}">
                        @error('client_name', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'client') is-invalid @enderror" id="clientContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="clientAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'client') is-invalid @enderror" id="clientAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary" id="clientSubmitBtn">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const stockOutModalEl = document.getElementById('stockOutModal');
        const clientModalEl = document.getElementById('clientModal');
        const form = document.getElementById('stockOutForm');
        const linesBody = document.getElementById('detailLinesBody');
        const template = document.getElementById('detailLineTemplate');
        const addLineBtn = document.getElementById('addDetailLineBtn');

        @if (session('new_client_id'))
            new bootstrap.Modal(stockOutModalEl).show();
        @elseif ($errors->stockout->any())
            new bootstrap.Modal(stockOutModalEl).show();
        @elseif ($errors->client->any())
            new bootstrap.Modal(clientModalEl).show();
        @endif

        const switchBtn = document.getElementById('switchToClientBtn');
        if (switchBtn) {
            switchBtn.addEventListener('click', function () {
                bootstrap.Modal.getInstance(stockOutModalEl)?.hide();
                new bootstrap.Modal(clientModalEl).show();
            });
        }

        let lineIndex = 0;

        const setLineError = function (row, field, message) {
            const el = row.querySelector('.line-' + field + '-error');
            if (!el) {
                return;
            }

            el.textContent = message || '';
            el.classList.toggle('d-none', !message);

            const input = row.querySelector(field === 'batch_id' ? '.line-batch'
                : field === 'unit_price' ? '.line-unit-price'
                : field === 'unit' ? '.line-unit'
                : '.line-quantity');
            if (input) {
                input.classList.toggle('is-invalid', Boolean(message));
            }
        };

        const refreshLine = function (row) {
            const batchSelect = row.querySelector('.line-batch');
            const scionEl = row.querySelector('.line-scion-variety');
            const hint = row.querySelector('.line-available-hint');
            const option = batchSelect.options[batchSelect.selectedIndex];

            if (batchSelect.value && option) {
                scionEl.textContent = option.getAttribute('data-scion-variety') || '—';
                hint.textContent = 'Available to release: ' + Number(option.getAttribute('data-available') || 0).toLocaleString() + '.';
            } else {
                scionEl.textContent = '—';
                hint.textContent = '';
            }

            const unitPrice = row.querySelector('.line-unit-price').value;
            const quantity = row.querySelector('.line-quantity').value;
            const amountEl = row.querySelector('.line-amount');

            if (unitPrice !== '' && quantity !== '') {
                const amount = Number(unitPrice) * Number(quantity);
                amountEl.textContent = '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                amountEl.textContent = '—';
            }
        };

        const addLine = function (values, errors) {
            values = values || {};
            errors = errors || {};

            const index = lineIndex++;
            const html = template.innerHTML.replace(/__INDEX__/g, index);
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;

            row.querySelector('.line-batch').value = values.batch_id || '';
            row.querySelector('.line-quantity').value = values.quantity || '';
            row.querySelector('.line-unit').value = values.unit || '{{\App\Models\StockOutDetail::UNITS[0]}}';
            row.querySelector('.line-unit-price').value = values.unit_price || '';

            linesBody.appendChild(row);
            refreshLine(row);

            ['batch_id', 'quantity', 'unit', 'unit_price'].forEach(function (field) {
                if (errors[field]) {
                    setLineError(row, field, errors[field]);
                }
            });

            return row;
        };

        const validate = function () {
            const rows = Array.from(linesBody.querySelectorAll('.detail-line'));
            const aggregates = {};
            let valid = rows.length > 0;

            rows.forEach(function (row) {
                ['batch_id', 'quantity', 'unit', 'unit_price'].forEach(function (field) {
                    setLineError(row, field, null);
                });

                const batchSelect = row.querySelector('.line-batch');
                const quantityInput = row.querySelector('.line-quantity');
                const option = batchSelect.options[batchSelect.selectedIndex];
                const batchId = batchSelect.value;
                const quantity = parseInt(quantityInput.value || '0', 10);

                if (!batchId) {
                    setLineError(row, 'batch_id', 'Please select a batch.');
                    valid = false;
                    return;
                }

                if (!quantityInput.value || quantity < 1) {
                    setLineError(row, 'quantity', 'Every line quantity must be at least 1.');
                    valid = false;
                }

                const available = parseInt(option.getAttribute('data-available') || '0', 10);
                if (quantity > available) {
                    setLineError(row, 'quantity', 'Quantity exceeds available inventory for ' + option.getAttribute('data-batch-code') + '.');
                    valid = false;
                }

                aggregates[batchId] = aggregates[batchId] || { available: available, code: option.getAttribute('data-batch-code'), total: 0, rows: [] };
                aggregates[batchId].total += quantity;
                aggregates[batchId].rows.push(row);
            });

            Object.keys(aggregates).forEach(function (batchId) {
                const aggregate = aggregates[batchId];
                if (aggregate.total > aggregate.available) {
                    aggregate.rows.forEach(function (row) {
                        setLineError(row, 'quantity', 'Quantity exceeds available inventory for ' + aggregate.code + '.');
                    });
                    valid = false;
                }
            });

            return valid;
        };

        linesBody.addEventListener('change', function (event) {
            const row = event.target.closest('.detail-line');
            if (!row) {
                return;
            }

            if (event.target.classList.contains('line-batch')) {
                setLineError(row, 'batch_id', null);
            }

            refreshLine(row);
            validate();
        });

        linesBody.addEventListener('input', function (event) {
            const row = event.target.closest('.detail-line');
            if (!row) {
                return;
            }

            if (event.target.classList.contains('line-quantity') || event.target.classList.contains('line-unit-price')) {
                refreshLine(row);
            }
        });

        linesBody.addEventListener('click', function (event) {
            const removeBtn = event.target.closest('.remove-line');
            if (!removeBtn) {
                return;
            }

            const row = removeBtn.closest('.detail-line');
            if (linesBody.querySelectorAll('.detail-line').length > 1) {
                row.remove();
            } else {
                row.querySelector('.line-batch').value = '';
                row.querySelector('.line-quantity').value = '';
                row.querySelector('.line-unit-price').value = '';
                refreshLine(row);
            }

            validate();
        });

        if (addLineBtn) {
            addLineBtn.addEventListener('click', function () {
                addLine();
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!validate()) {
                    event.preventDefault();
                    return;
                }

                if (!window.confirm('Are you sure you want to record this stock-out transaction?')) {
                    event.preventDefault();
                    return;
                }

                const btn = document.getElementById('stockOutSubmitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }

        // Repopulate the form from old input (after a failed submission) or
        // seed one empty line for a fresh form.
        const oldDetails = @json($oldDetails);
        const oldErrors = @json($oldLineErrors);

        if (oldDetails && Object.keys(oldDetails).length) {
            Object.keys(oldDetails).forEach(function (key) {
                addLine(oldDetails[key], oldErrors[key] || {});
            });
        } else {
            addLine();
        }

        const clientForm = document.getElementById('clientForm');
        if (clientForm) {
            clientForm.addEventListener('submit', function () {
                const btn = document.getElementById('clientSubmitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            });
        }
    });
</script>
@endpush
