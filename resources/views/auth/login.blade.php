<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cacao Track</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-light);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background-color: #fff;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: var(--primary-color);
        }
        .login-header .login-logo {
            width: 140px;
            height: auto;
            margin-bottom: 1rem;
        }
        .login-btn {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .login-btn:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .input-group-text {
            background-color: #fff;
            border-left: none;
            cursor: pointer;
        }
        .form-control.password-input {
            border-right: none;
        }
        .form-control:focus, .input-group-text:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body class="login-page">

    <div class="card login-card p-4">
        <div class="card-body">
            <div class="login-header">
                <img src="{{ asset('images/cacao-logo.jpg') }}" alt="RU-CHRIS CRIOLLO Cacao Agriventures Inc." class="login-logo">
                <h4 class="fw-bold mb-0">CACAO TRACK</h4>
                <p class="text-muted small mt-1">Cacao Mortality Tracking and Inventory System</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger small" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="loginForm" method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label text-muted small fw-bold">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" id="email" placeholder="admin@cacaotrack.test" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label text-muted small fw-bold">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control password-input @error('password') is-invalid @enderror" id="password" placeholder="••••••••" required>
                        <span class="input-group-text" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </span>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" name="remember" value="1" class="form-check-input" id="rememberMe">
                    <label class="form-check-label text-muted small" for="rememberMe">Remember Me</label>
                </div>

                <button type="submit" class="btn btn-primary login-btn w-100 py-2">
                    LOGIN
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Password show/hide functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            }
        });
    </script>
</body>
</html>
