<header class="top-navbar">
    <div class="d-flex align-items-center">
        <button id="sidebarToggle" class="me-3">
            <i class="bi bi-list"></i>
        </button>
        <span class="d-none d-md-inline text-muted">Cacao Mortality Tracking and Inventory System</span>
    </div>
    
    <div class="d-flex align-items-center">
        <!-- User Profile -->
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                    <i class="bi bi-person"></i>
                </div>
                <span class="d-none d-md-inline">{{ Auth::user()->name ?? 'Guest' }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item" href="{{ route('profile.index') }}"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
