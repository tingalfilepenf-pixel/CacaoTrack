{{-- User Management (FR-02). A user's role name and access restrictions are
    stored directly on the account (`users.role` + `users.permissions`) after the
    approved denormalization (ERD §2.1) — there is no separate Roles table.
    Chain: UI → users.index / users.manage.store / users.update / users.destroy
    → UserController → User::management*Rules() (RULE 6) → users table → real view.
    Validation error bag: `user`; modal reopen via old('edit_user_id').
    Access restrictions: App\Models\Role::PERMISSIONS. Passwords are never displayed.
--}}
@extends('layouts.app')

@section('title', 'User Management')
@section('page_title', 'Users')

@php
    $viewer = Auth::user();
    $isAdmin = $viewer && $viewer->hasPermission('manage_users');
    $userList = $users ?? null;
    $filterValues = $filters ?? [];
    $roleOptions = $roleOptions ?? collect();
    $permissionCatalog = $permissionCatalog ?? \App\Models\Role::PERMISSIONS;
    $roleCounts = $roleCounts ?? collect();
    $totalUsers = $totalUsers ?? ($userList ? $userList->total() : 0);
    $administratorsCount = $administratorsCount ?? 0;
    $managerCount = (int) ($roleCounts['Manager'] ?? 0);
    $staffCount = (int) ($roleCounts['Staff'] ?? 0);
    $otherRolesCount = max(0, $totalUsers - $administratorsCount - $managerCount - $staffCount);
    $indexAction = route('users.index');

    $roleBadge = function ($roleName) {
        return match ($roleName) {
            'Administrator' => 'bg-danger',
            'Manager' => 'bg-primary',
            'Staff' => 'bg-success',
            default => 'bg-secondary',
        };
    };

    $permissionLabels = function ($keys) use ($permissionCatalog) {
        return collect($keys)->map(fn ($key) => $permissionCatalog[$key] ?? $key)->values();
    };
@endphp

@section('page_actions')
    @if ($isAdmin)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg me-1"></i> Add User
        </button>
    @endif
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

@if (! $isAdmin)
    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="bi bi-shield-lock me-2 mt-1"></i>
        <div>You are signed in as <strong>{{ $viewer->roleLabel() }}</strong>. User management is read-only for your role — Add/Edit/Delete actions are restricted to holders of the <code>manage_users</code> access restriction (FR-02).</div>
    </div>
@endif

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Users</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($totalUsers) }}</div>
                <div class="text-muted small">Accounts in the current filters</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Administrators</div>
                <div class="fs-3 fw-bold text-danger">{{ number_format($administratorsCount) }}</div>
                <div class="text-muted small">Full access incl. user management</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Managers</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format($managerCount) }}</div>
                <div class="text-muted small">Operational oversight</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Other Roles</div>
                <div class="fs-3 fw-bold">{{ number_format($otherRolesCount) }}</div>
                <div class="text-muted small">Staff and custom role names</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ $indexAction }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filterSearch" class="form-label text-muted small fw-bold mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="filterSearch" name="search" value="{{ $filterValues['search'] ?? '' }}" placeholder="Name or email...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterRole" class="form-label text-muted small fw-bold mb-1">Role</label>
                <select class="form-select form-select-sm" id="filterRole" name="role">
                    <option value="">All Roles</option>
                    @foreach ($roleOptions as $roleName)
                        <option value="{{ $roleName }}" @selected(($filterValues['role'] ?? '') === $roleName)>{{ $roleName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ $indexAction }}" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">User Directory</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="userTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>ROLE</th>
                        <th>ACCESS RESTRICTIONS</th>
                        <th>MEMBER SINCE</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($userList ?? [] as $user)
                        @php
                            $isSelf = $viewer && (int) $viewer->getKey() === (int) $user->getKey();
                            $userPermissions = $user->permissions ?? [];
                            $details = [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'role' => $user->roleLabel(),
                                'member_since' => $user->created_at ? $user->created_at->format('F j, Y') : null,
                                'activities' => $user->activity_logs_count ?? null,
                                'permissions' => $permissionLabels($userPermissions),
                            ];
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-primary">USR-{{ $user->id }}</td>
                            <td class="fw-medium">
                                <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px; font-size: 0.8rem;">{{ strtoupper(substr($user->name ?? '?', 0, 1)) }}</span>{{ $user->name }}
                                @if ($isSelf)
                                    <span class="badge bg-light text-dark border ms-1">You</span>
                                @endif
                            </td>
                            <td class="text-muted text-break">{{ $user->email }}</td>
                            <td><span class="badge {{ $roleBadge($user->roleLabel()) }}">{{ $user->roleLabel() }}</span></td>
                            <td>
                                @forelse ($userPermissions as $key)
                                    <span class="badge bg-light text-dark border mb-1">{{ $permissionCatalog[$key] ?? $key }}</span>
                                @empty
                                    <span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>No access restrictions</span>
                                @endforelse
                            </td>
                            <td class="text-muted">{{ $user->created_at ? $user->created_at->format('M j, Y') : '—' }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#userDetailsModal"
                                    data-details="{{ json_encode($details) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @if ($isAdmin)
                                    <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-email="{{ $user->email }}"
                                        data-user-role="{{ $user->roleLabel() }}"
                                        data-user-permissions="{{ json_encode(array_values($userPermissions)) }}" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-email="{{ $user->email }}"
                                        data-is-self="{{ $isSelf ? '1' : '0' }}" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-3 d-block mb-2"></i>
                                No users found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($userList && method_exists($userList, 'links'))
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top flex-wrap gap-2">
            <span class="text-muted small">
                Showing {{ $userList->firstItem() ?? 0 }} to {{ $userList->lastItem() ?? 0 }} of {{ $userList->total() }} entries
            </span>
            {{ $userList->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

<datalist id="roleOptionsList">
    @foreach (\App\Models\Role::ROLES as $builtInRole)
        <option value="{{ $builtInRole }}"></option>
    @endforeach
</datalist>

@if ($isAdmin)
<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm" method="POST" action="{{ route('users.manage.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addUserName">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name', 'user') is-invalid @enderror" id="addUserName"
                                name="name" maxlength="255" required autocomplete="off" value="{{ old('name') }}">
                            @error('name', 'user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addUserEmail">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'user') is-invalid @enderror" id="addUserEmail"
                                name="email" maxlength="255" required autocomplete="off" value="{{ old('email') }}">
                            @error('email', 'user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="addUserRole">Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('role', 'user') is-invalid @enderror" id="addUserRole"
                            name="role" list="roleOptionsList" maxlength="100" required autocomplete="off"
                            value="{{ old('role', \App\Models\Role::DEFAULT_ROLE) }}" placeholder="e.g. Manager">
                        @error('role', 'user')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Choose a built-in role or type a custom role name.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Access Restrictions <span class="text-danger">*</span></label>
                        @error('permissions', 'user')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        @error('permissions.0', 'user')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        <div class="border rounded p-3">
                            @foreach ($permissionCatalog as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}"
                                        id="addPermission_{{ $key }}" @checked(in_array($key, old('permissions', []), true))>
                                    <label class="form-check-label" for="addPermission_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">At least one access restriction is required. Administrator always keeps Manage Users &amp; Roles.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addUserPassword">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password', 'user') is-invalid @enderror" id="addUserPassword"
                                name="password" required minlength="8" autocomplete="new-password">
                            @error('password', 'user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">At least 8 characters. Never displayed after saving.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="addUserPasswordConfirmation">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="addUserPasswordConfirmation"
                                name="password_confirmation" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addUserSubmitBtn">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @php
                $editTemplate = route('users.update', ['user' => '__ID__']);
            @endphp
            <form id="editUserForm" method="POST" action="{{ $editTemplate }}" data-action-template="{{ $editTemplate }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="editUserName">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name', 'user') is-invalid @enderror" id="editUserName"
                                name="name" maxlength="255" required autocomplete="off" value="{{ old('name') }}">
                            @error('name', 'user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold" for="editUserEmail">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'user') is-invalid @enderror" id="editUserEmail"
                                name="email" maxlength="255" required autocomplete="off" value="{{ old('email') }}">
                            @error('email', 'user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="editUserRole">Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('role', 'user') is-invalid @enderror" id="editUserRole"
                            name="role" list="roleOptionsList" maxlength="100" required autocomplete="off">
                        @error('role', 'user')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Use caution when changing your own role — demoting your own Administrator account may lock you out of management.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small fw-bold">Access Restrictions <span class="text-danger">*</span></label>
                        @error('permissions', 'user')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        @error('permissions.0', 'user')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        <div class="border rounded p-3">
                            @foreach ($permissionCatalog as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}"
                                        id="editPermission_{{ $key }}">
                                    <label class="form-check-label" for="editPermission_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">At least one access restriction is required. Administrator always keeps Manage Users &amp; Roles.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editUserSubmitBtn">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @php
                $deleteTemplate = route('users.destroy', ['user' => '__ID__']);
            @endphp
            <form id="deleteUserForm" method="POST" action="{{ $deleteTemplate }}" data-action-template="{{ $deleteTemplate }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to delete <strong id="deleteUserName">&mdash;</strong> (<span id="deleteUserEmail" class="text-muted">&mdash;</span>)?</p>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Users with operational records (batches, mortality, stock, activity history) cannot be deleted — the backend enforces this with <code>restrictOnDelete</code>. You also cannot delete your own signed-in account.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteUserSubmitBtn">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">User Details: <span id="detailsId">&mdash;</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Full Name:</div>
                    <div class="col-7" id="detailsName">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Email:</div>
                    <div class="col-7 text-break" id="detailsEmail">&mdash;</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Role:</div>
                    <div class="col-7" id="detailsRole">&mdash;</div>
                </div>
                <hr>
                <div class="text-muted small fw-bold mb-2">Access Restrictions:</div>
                <div id="detailsPermissions">&mdash;</div>
                <hr>
                <div class="row mb-2">
                    <div class="col-5 text-muted small fw-bold">Member Since:</div>
                    <div class="col-7" id="detailsMemberSince">&mdash;</div>
                </div>
                <div class="row">
                    <div class="col-5 text-muted small fw-bold">Activity Records:</div>
                    <div class="col-7" id="detailsActivities">&mdash;</div>
                </div>
                <div class="form-text mt-3"><i class="bi bi-shield-lock me-1"></i>Passwords are never displayed (FR-02 / §21).</div>
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
        const addModalEl = document.getElementById('addUserModal');
        const editModalEl = document.getElementById('editUserModal');
        const deleteModalEl = document.getElementById('deleteUserModal');
        const detailsModalEl = document.getElementById('userDetailsModal');

        @if (old('edit_user_id'))
            if (editModalEl) { new bootstrap.Modal(editModalEl).show(); }
        @elseif ($errors->user->any())
            if (addModalEl) { new bootstrap.Modal(addModalEl).show(); }
        @endif

        const setText = function (id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value == null || value === '' ? '—' : value;
            }
        };

        const applyAction = function (form, id) {
            if (!form) { return; }
            const template = form.getAttribute('data-action-template') || form.getAttribute('action');
            if (template && id) {
                form.setAttribute('action', template.replace('__ID__', id));
            }
        };

        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) { return; }

                applyAction(document.getElementById('editUserForm'), button.getAttribute('data-user-id'));
                document.getElementById('editUserName').value = button.getAttribute('data-user-name') || '';
                document.getElementById('editUserEmail').value = button.getAttribute('data-user-email') || '';
                document.getElementById('editUserRole').value = button.getAttribute('data-user-role') || '';

                let permissions = [];
                try { permissions = JSON.parse(button.getAttribute('data-user-permissions') || '[]'); } catch (error) { permissions = []; }

                document.querySelectorAll('#editUserForm input[name="permissions[]"]').forEach(function (checkbox) {
                    checkbox.checked = permissions.includes(checkbox.value);
                });
            });
        }

        if (deleteModalEl) {
            deleteModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) { return; }

                applyAction(document.getElementById('deleteUserForm'), button.getAttribute('data-user-id'));
                setText('deleteUserName', button.getAttribute('data-user-name'));
                setText('deleteUserEmail', button.getAttribute('data-user-email'));

                const isSelf = button.getAttribute('data-is-self') === '1';
                const submitBtn = document.getElementById('deleteUserSubmitBtn');
                if (submitBtn && !submitBtn.hasAttribute('disabled')) {
                    submitBtn.disabled = isSelf;
                    submitBtn.title = isSelf ? 'You cannot delete your own signed-in account.' : '';
                }
            });
        }

        if (detailsModalEl) {
            detailsModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) { return; }

                let details = {};
                try { details = JSON.parse(button.getAttribute('data-details') || '{}'); } catch (error) { details = {}; }

                setText('detailsId', details.id != null ? 'USR-' + details.id : null);
                setText('detailsName', details.name);
                setText('detailsEmail', details.email);
                setText('detailsRole', details.role);
                setText('detailsMemberSince', details.member_since);
                setText('detailsActivities', details.activities != null ? Number(details.activities).toLocaleString() : null);

                const container = document.getElementById('detailsPermissions');
                if (container) {
                    const permissions = Array.isArray(details.permissions) ? details.permissions : [];
                    if (permissions.length === 0) {
                        container.innerHTML = '<span class="text-danger">No access restrictions defined.</span>';
                    } else {
                        container.innerHTML = permissions.map(function (label) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-light text-dark border me-1 mb-1';
                            span.textContent = label;
                            return span.outerHTML;
                        }).join('');
                    }
                }
            });
        }

        const guardSubmit = function (formId, buttonId, label) {
            const form = document.getElementById(formId);
            const button = document.getElementById(buttonId);
            if (!form || !button) { return; }

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + label;
            });
        };

        guardSubmit('addUserForm', 'addUserSubmitBtn', 'Saving...');
        guardSubmit('editUserForm', 'editUserSubmitBtn', 'Saving...');
    });
</script>
@endpush
