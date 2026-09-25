@extends('layouts.app')

@section('title', 'Client Management')
@section('page_title', 'Clients')

@section('page_actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
    <i class="bi bi-plus-lg me-1"></i> Add Client
</button>
@endsection

@section('content')
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

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? '' : 'active' }}" href="{{ route('inventory.clients', array_filter(['search' => $filters['search'] ?? null])) }}">
            <i class="bi bi-list-ul me-1"></i> Active
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'archived' ? 'active' : '' }}" href="{{ route('inventory.clients', array_filter(['search' => $filters['search'] ?? null, 'tab' => 'archived'])) }}">
            <i class="bi bi-archive me-1"></i> Archived
            @if ($archivedCount > 0)
                <span class="badge bg-secondary ms-1">{{ $archivedCount }}</span>
            @endif
        </a>
    </li>
</ul>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Clients</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalClients) }}</div>
                <div class="text-muted small">Clients in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">With Stock-Out Activity</div>
                <div class="fs-3 fw-bold">{{ number_format($clientsWithActivity) }}</div>
                <div class="text-muted small">Clients that have received stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Stock-Out Transactions</div>
                <div class="fs-3 fw-bold">{{ number_format($totalStockOuts) }}</div>
                <div class="text-muted small">Recorded against these clients</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.clients') }}" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="{{ $status }}">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Client name, contact, or address...">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventory.clients') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Clients Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            @if ($status === 'archived')
                <i class="bi bi-archive me-2"></i>Archived Clients
            @else
                Client Directory
            @endif
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="clientTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>CLIENT NAME</th>
                        <th>CONTACT NUMBER</th>
                        <th>ADDRESS</th>
                        <th>STOCK-OUTS</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $details = [
                                'reference' => $client->reference(),
                                'client_name' => $client->client_name,
                                'contact_number' => $client->contact_number,
                                'address' => $client->address,
                                'stock_outs' => (int) $client->stock_outs_count,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">{{ $client->reference() }}</td>
                            <td class="fw-medium">
                                {{ $client->client_name }}
                                @if ($status === 'archived' && $client->archived_at)
                                    <div class="text-muted small fw-normal">Archived {{ $client->archived_at->format('M j, Y g:i A') }}</div>
                                @endif
                            </td>
                            <td>{{ $client->contact_number }}</td>
                            <td>{{ $client->address }}</td>
                            <td>
                                @if ($client->stock_outs_count > 0)
                                    <span class="badge bg-success">{{ number_format($client->stock_outs_count) }}</span>
                                @else
                                    <span class="badge bg-secondary">0</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#clientDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editClientModal"
                                    data-client-id="{{ $client->client_id }}"
                                    data-client-name="{{ $client->client_name }}"
                                    data-contact="{{ $client->contact_number }}"
                                    data-address="{{ $client->address }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('inventory.clients.restore', $client) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#archiveClientModal"
                                        data-client-id="{{ $client->client_id }}"
                                        data-client-name="{{ $client->client_name }}" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                @if ($status === 'archived')
                                    No archived clients. Archived clients appear here and can be restored.
                                @else
                                    No clients found for the current filters.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
        <span class="text-muted small">
            Showing {{ $clients->firstItem() ?? 0 }} to {{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }} entries
        </span>
        {{ $clients->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Client</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addClientForm" method="POST" action="{{ route('inventory.clients.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addClientName">Client Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('client_name', 'client') is-invalid @enderror" id="addClientName"
                            name="client_name" maxlength="255" required value="{{ old('client_name') }}">
                        @error('client_name', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addClientContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'client') is-invalid @enderror" id="addClientContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addClientAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'client') is-invalid @enderror" id="addClientAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addClientSubmitBtn">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Client Modal -->
<div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editClientForm" method="POST" action="{{ route('inventory.clients.update', ['client' => '__ID__']) }}" data-action-template="{{ route('inventory.clients.update', ['client' => '__ID__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editClientName">Client Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('client_name', 'client') is-invalid @enderror" id="editClientName"
                            name="client_name" maxlength="255" required value="{{ old('client_name') }}">
                        @error('client_name', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editClientContact">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('contact_number', 'client') is-invalid @enderror" id="editClientContact"
                            name="contact_number" maxlength="255" required value="{{ old('contact_number') }}">
                        @error('contact_number', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editClientAddress">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('address', 'client') is-invalid @enderror" id="editClientAddress"
                            name="address" maxlength="255" required value="{{ old('address') }}">
                        @error('address', 'client')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editClientSubmitBtn">Update Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Client Modal -->
<div class="modal fade" id="archiveClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Archive Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archiveClientForm" method="POST" action="{{ route('inventory.clients.archive', ['client' => '__ID__']) }}" data-action-template="{{ route('inventory.clients.archive', ['client' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Archive <strong id="archiveClientName">&mdash;</strong>?</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Archiving hides the client from the active directory and from the
                        New Stock Out form, but all existing stock-out records keep their history. You can restore it anytime from
                        the Archived tab.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Client Details Modal -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Client Details: <span id="detailsReference">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Client Name:</div>
                    <div class="col-7" id="detailsName">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Contact Number:</div>
                    <div class="col-7" id="detailsContact">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Address:</div>
                    <div class="col-7" id="detailsAddress">&mdash;</div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Stock-Out Records:</div>
                    <div class="col-7" id="detailsStockOuts">&mdash;</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const addModalEl = document.getElementById('addClientModal');
        const editModalEl = document.getElementById('editClientModal');
        const archiveModalEl = document.getElementById('archiveClientModal');
        const detailsModalEl = document.getElementById('clientDetailsModal');

        @if (old('edit_client_id'))
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->client->any())
            new bootstrap.Modal(addModalEl).show();
        @endif

        const setText = function (id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value == null || value === '' ? '—' : value;
            }
        };

        const applyAction = function (form, id) {
            const template = form.getAttribute('data-action-template') || form.getAttribute('action');
            form.setAttribute('action', template.replace('__ID__', id));
        };

        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                const id = button.getAttribute('data-client-id');
                applyAction(document.getElementById('editClientForm'), id);
                document.getElementById('editClientName').value = button.getAttribute('data-client-name') || '';
                document.getElementById('editClientContact').value = button.getAttribute('data-contact') || '';
                document.getElementById('editClientAddress').value = button.getAttribute('data-address') || '';
            });
        }

        if (archiveModalEl) {
            archiveModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                applyAction(document.getElementById('archiveClientForm'), button.getAttribute('data-client-id'));
                setText('archiveClientName', button.getAttribute('data-client-name'));
            });
        }

        if (detailsModalEl) {
            detailsModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                let details = {};
                try {
                    details = JSON.parse(button.getAttribute('data-details') || '{}');
                } catch (error) {
                    details = {};
                }

                setText('detailsReference', details.reference);
                setText('detailsName', details.client_name);
                setText('detailsContact', details.contact_number);
                setText('detailsAddress', details.address);
                setText('detailsStockOuts', Number(details.stock_outs || 0).toLocaleString());
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

        guardSubmit('addClientForm', 'addClientSubmitBtn', 'Saving...');
        guardSubmit('editClientForm', 'editClientSubmitBtn', 'Saving...');
    });
</script>
@endpush
