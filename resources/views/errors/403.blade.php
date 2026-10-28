@php
    $role = auth()->user()?->roleLabel() ?? 'Guest';
    $requiredPermission = request()->hasSession()
        ? request()->session()->get('required_permission')
        : null;
    $dashboardUrl = auth()->check() ? route('dashboard') : route('login');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access restricted - CacaoTrack</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f3; color: #212529; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: min(34rem, calc(100% - 2rem)); background: #fff; border-radius: .75rem; box-shadow: 0 10px 30px rgba(33, 37, 41, .12); overflow: hidden; }
        .bar { background: #198754; height: .35rem; }
        .body { padding: 2rem; }
        .eyebrow { color: #198754; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 .5rem; }
        h1 { font-size: 1.5rem; margin: 0 0 1rem; }
        p { line-height: 1.55; margin: 0 0 .85rem; }
        .detail { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: .5rem; padding: .85rem 1rem; margin: 1rem 0 1.25rem; font-size: .9rem; }
        .detail strong { display: inline-block; min-width: 9rem; color: #495057; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn { display: inline-block; padding: .55rem .95rem; border-radius: .4rem; text-decoration: none; font-weight: 600; font-size: .9rem; }
        .btn-primary { background: #198754; color: #fff; }
        .btn-secondary { background: #fff; color: #495057; border: 1px solid #ced4da; }
        .note { color: #6c757d; font-size: .85rem; margin: 1rem 0 0; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="bar"></div>
        <div class="body">
            <p class="eyebrow">CacaoTrack</p>
            <h1>This area isn't available for your role</h1>
            <p>Your account is signed in, but it does not have the access needed to open this page.</p>
            <div class="detail">
                <div><strong>Your role:</strong> {{ $role }}</div>
                <div><strong>Required access:</strong> {{ $requiredPermission ?? 'the required access' }}</div>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="{{ $dashboardUrl }}">Back to Dashboard</a>
                @if (auth()->check())
                    <a class="btn btn-secondary" href="{{ route('profile.index') }}">View Profile</a>
                @endif
            </div>
            <p class="note">Ask an Administrator if you need access.</p>
        </div>
    </main>
</body>
</html>
