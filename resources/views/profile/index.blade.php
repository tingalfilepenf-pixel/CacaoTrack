@extends('layouts.app')

@section('title', 'User Profile')
@section('page_title', 'User Profile')

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

<div class="row">
    <div class="col-lg-4 col-md-5 mb-4 mb-md-0">
        <!-- Profile Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h4 class="mb-1 fw-bold">{{ $user->name }}</h4>
                <p class="text-muted mb-3">{{ $user->roleLabel() }}</p>

                <span class="badge bg-success rounded-pill px-3 py-2 mb-4">
                    <i class="bi bi-check-circle me-1"></i> Active Account
                </span>

                <hr>

                <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="bi bi-pencil-square me-1"></i> Edit Profile
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-md-7">
        <!-- Account Details -->
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Account Details</h6>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-sm-3 text-muted fw-bold">Full Name</div>
                    <div class="col-sm-9">{{ $user->name }}</div>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-3 text-muted fw-bold">Email Address</div>
                    <div class="col-sm-9">{{ $user->email }}</div>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-3 text-muted fw-bold">System Role</div>
                    <div class="col-sm-9">
                        <span class="badge bg-primary">{{ $user->roleLabel() }}</span>
                        <div class="form-text">Roles are assigned under Users management.</div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-3 text-muted fw-bold">Member Since</div>
                    <div class="col-sm-9">{{ $user->created_at ? $user->created_at->format('F j, Y') : '—' }}</div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3">Security</h6>
                <div class="row">
                    <div class="col-sm-3 text-muted fw-bold">Password</div>
                    <div class="col-sm-9">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editProfileForm" method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="profileName">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="profileName" name="name" maxlength="255" required
                            class="form-control @error('name', 'profile') is-invalid @enderror"
                            value="{{ old('name', $user->name) }}">
                        @error('name', 'profile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="profileEmail">Email Address <span class="text-danger">*</span></label>
                        <input type="email" id="profileEmail" name="email" maxlength="255" required
                            class="form-control @error('email', 'profile') is-invalid @enderror"
                            value="{{ old('email', $user->email) }}">
                        @error('email', 'profile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editProfileSubmitBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="changePasswordForm" method="POST" action="{{ route('profile.password.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="currentPassword">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input type="password" id="currentPassword" name="current_password" required autocomplete="current-password"
                                class="form-control @error('current_password', 'password') is-invalid @enderror">
                            <span class="input-group-text toggle-password" role="button" tabindex="0" aria-label="Show password"><i class="bi bi-eye"></i></span>
                            @error('current_password', 'password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="newPassword">New Password <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input type="password" id="newPassword" name="password" required minlength="8" autocomplete="new-password"
                                class="form-control @error('password', 'password') is-invalid @enderror">
                            <span class="input-group-text toggle-password" role="button" tabindex="0" aria-label="Show password"><i class="bi bi-eye"></i></span>
                            @error('password', 'password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" for="newPasswordConfirmation">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="newPasswordConfirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                class="form-control">
                            <span class="input-group-text toggle-password" role="button" tabindex="0" aria-label="Show password"><i class="bi bi-eye"></i></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="changePasswordSubmitBtn">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editModalEl = document.getElementById('editProfileModal');
        const passwordModalEl = document.getElementById('changePasswordModal');

        @if ($errors->profile->any())
            new bootstrap.Modal(editModalEl).show();
        @elseif ($errors->password->any())
            new bootstrap.Modal(passwordModalEl).show();
        @endif

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

        guardSubmit('editProfileForm', 'editProfileSubmitBtn', 'Saving...');
        guardSubmit('changePasswordForm', 'changePasswordSubmitBtn', 'Updating...');
    });
</script>
@endpush
@endsection
