<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Cacao Track</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- App shell -->
    <div class="d-flex min-vh-100">
        <!-- Sidebar -->
        @include('layouts.sidebar')

        <!-- Main Content -->
        <div id="mainContent" class="flex-grow-1">
            <!-- Navbar -->
            @include('layouts.navbar')

            <!-- Page Content -->
            <main class="content-wrapper">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">@yield('page_title', 'Dashboard')</h1>
                    @yield('page_actions')
                </div>

                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.toasts')

    @stack('scripts')
</body>
</html>
