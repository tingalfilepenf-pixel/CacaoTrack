@php
    $dashboardUrl = auth()->check() ? route('dashboard') : route('login');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found - CacaoTrack</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f3; color: #212529; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: min(34rem, calc(100% - 2rem)); background: #fff; border-radius: .75rem; box-shadow: 0 10px 30px rgba(33, 37, 41, .12); overflow: hidden; }
        .bar { background: #198754; height: .35rem; }
        .body { padding: 2rem; }
        .eyebrow { color: #198754; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 .5rem; }
        h1 { font-size: 1.5rem; margin: 0 0 1rem; }
        p { line-height: 1.55; margin: 0 0 .85rem; }
        .actions { margin-top: 1.25rem; }
        .btn { display: inline-block; padding: .55rem .95rem; border-radius: .4rem; text-decoration: none; font-weight: 600; font-size: .9rem; background: #198754; color: #fff; }
        .note { color: #6c757d; font-size: .85rem; margin: 1rem 0 0; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="bar"></div>
        <div class="body">
            <p class="eyebrow">CacaoTrack</p>
            <h1>We couldn't find that page</h1>
            <p>The page may have moved, or the link you followed may be out of date.</p>
            <p class="note">Check the address or use the button below to return to the dashboard.</p>
            <div class="actions">
                <a class="btn" href="{{ $dashboardUrl }}">Back to Dashboard</a>
            </div>
        </div>
    </main>
</body>
</html>
