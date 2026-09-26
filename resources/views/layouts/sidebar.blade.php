@php
    $currentUser = Auth::user();

    // Access restrictions (ERD §2.1/§9.7) live on the signed-in user
    // (`users.permissions`). The sidebar renders only the areas the user is
    // allowed to use; the same restrictions are enforced server-side by the
    // `permission` middleware (FR-02). Hiding a link here is a convenience
    // only — it is never the security boundary.
    $canRecordOperations = $currentUser?->hasPermission('record_operations') ?? false;
    $canManageInventory = $currentUser?->hasPermission('manage_inventory') ?? false;
    $canManageStock = $currentUser?->hasPermission('manage_stock') ?? false;
    $canViewAnalytics = $currentUser?->hasPermission('view_analytics') ?? false;
    $canViewActivity = $currentUser?->hasPermission('view_activity') ?? false;
    $canManageUsers = $currentUser?->hasPermission('manage_users') ?? false;

    $managementOpen = request()->routeIs([
        'inventory.suppliers',
        'inventory.clients',
    ]);
    $tabularOpen = request()->routeIs([
        'analytics.stock-ledger',
        'analytics.mortality-matrix',
    ]);
    $historicalOpen = request()->routeIs([
        'analytics.historical.seasonality',
        'analytics.historical.age-profile',
        'analytics.historical.gap-report',
    ]);
@endphp
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-tree-fill text-success me-2"></i>
        <span>CACAO TRACK</span>
    </div>
    
    <div class="nav flex-column py-2">
        <a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <!-- Operational -->
        @if ($canRecordOperations || $canManageInventory)
            <h6 class="nav-title">Operational</h6>
            @if ($canRecordOperations)
                <a href="{{ route('operational.checklist') }}" class="nav-link {{ request()->routeIs('operational.checklist') ? 'active' : '' }}">
                    <i class="bi bi-check-square"></i>
                    <span>Daily Checklist</span>
                </a>
                <a href="{{ route('operational.mortality') }}" class="nav-link {{ request()->routeIs('operational.mortality') ? 'active' : '' }}">
                    <i class="bi bi-heart-pulse"></i>
                    <span>Mortality Tracking</span>
                </a>
                <a href="{{ route('operational.replanting') }}" class="nav-link {{ request()->routeIs('operational.replanting') ? 'active' : '' }}">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Regraft</span>
                </a>
                <a href="{{ route('operational.batches') }}" class="nav-link {{ request()->routeIs('operational.batches') ? 'active' : '' }}">
                    <i class="bi bi-boxes"></i>
                    <span>Batches</span>
                </a>
            @endif
            @if ($canManageInventory)
                <a href="{{ route('operational.scion-varieties') }}" class="nav-link {{ request()->routeIs('operational.scion-varieties') ? 'active' : '' }}">
                    <i class="bi bi-tags"></i>
                    <span>Scion Variety</span>
                </a>
                <a href="{{ route('operational.scion-receivings') }}" class="nav-link {{ request()->routeIs('operational.scion-receivings') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <span>Scion Receiving</span>
                </a>
            @endif
        @endif

        <!-- Inventory -->
        @if ($canManageInventory || $canManageStock)
            <h6 class="nav-title">Inventory</h6>
            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i>
                <span>Inventory</span>
            </a>
            @if ($canManageStock)
                <a href="{{ route('inventory.stock-in') }}" class="nav-link {{ request()->routeIs('inventory.stock-in') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Stock In</span>
                </a>
                <a href="{{ route('inventory.stock-out') }}" class="nav-link {{ request()->routeIs('inventory.stock-out') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Stock Out</span>
                </a>
            @endif
        @endif

        <!-- Management -->
        @if ($canManageInventory)
            <a class="nav-link {{ $managementOpen ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#inventoryManageCollapse" role="button" aria-expanded="{{ $managementOpen ? 'true' : 'false' }}" aria-controls="inventoryManageCollapse">
                <i class="bi bi-folder2-open"></i>
                <span>Management</span>
                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
            </a>
            <div class="collapse {{ $managementOpen ? 'show' : '' }}" id="inventoryManageCollapse">
                <div class="nav flex-column ms-3" style="font-size: 0.9rem;">
                    <a href="{{ route('inventory.suppliers') }}" class="nav-link py-1 {{ request()->routeIs('inventory.suppliers') ? 'text-white' : '' }}"><i class="bi bi-truck me-2"></i>Supplier</a>
                    <a href="{{ route('inventory.clients') }}" class="nav-link py-1 {{ request()->routeIs('inventory.clients') ? 'text-white' : '' }}"><i class="bi bi-people me-2"></i>Client</a>
                </div>
            </div>
        @endif

        <!-- Analytics -->
        @if ($canViewAnalytics)
            <h6 class="nav-title">Analytics</h6>
            <a href="{{ route('analytics.index') }}" class="nav-link {{ request()->routeIs('analytics.index') ? 'active' : '' }}">
                <i class="bi bi-graph-up"></i>
                <span>Operational Analytics</span>
            </a>
            
            <!-- Tabular Deliverables -->
            <a class="nav-link {{ $tabularOpen ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#tabularCollapse" role="button" aria-expanded="{{ $tabularOpen ? 'true' : 'false' }}" aria-controls="tabularCollapse">
                <i class="bi bi-table"></i>
                <span>Tabular Deliverables</span>
                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
            </a>
            <div class="collapse {{ $tabularOpen ? 'show' : '' }}" id="tabularCollapse">
                <div class="nav flex-column ms-3" style="font-size: 0.9rem;">
                    <a href="{{ route('analytics.stock-ledger') }}" class="nav-link py-1 {{ request()->routeIs('analytics.stock-ledger') ? 'text-white' : '' }}"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Stock Ledger</a>
                    <a href="{{ route('analytics.mortality-matrix') }}" class="nav-link py-1 {{ request()->routeIs('analytics.mortality-matrix') ? 'text-white' : '' }}"><i class="bi bi-grid-3x3 me-2"></i>Root Cause Matrix</a>
                </div>
            </div>

            <!-- Historical Analytics -->
            <a class="nav-link {{ $historicalOpen ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#historicalCollapse" role="button" aria-expanded="{{ $historicalOpen ? 'true' : 'false' }}" aria-controls="historicalCollapse">
                <i class="bi bi-clock-history"></i>
                <span>Historical</span>
                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
            </a>
            <div class="collapse {{ $historicalOpen ? 'show' : '' }}" id="historicalCollapse">
                <div class="nav flex-column ms-3" style="font-size: 0.9rem;">
                    <a href="{{ route('analytics.historical.seasonality') }}" class="nav-link py-1 {{ request()->routeIs('analytics.historical.seasonality') ? 'text-white' : '' }}"><i class="bi bi-thermometer-half me-2"></i>Seasonality Heatmap</a>
                    <a href="{{ route('analytics.historical.age-profile') }}" class="nav-link py-1 {{ request()->routeIs('analytics.historical.age-profile') ? 'text-white' : '' }}"><i class="bi bi-bar-chart-steps me-2"></i>Age Profile</a>
                    <a href="{{ route('analytics.historical.gap-report') }}" class="nav-link py-1 {{ request()->routeIs('analytics.historical.gap-report') ? 'text-white' : '' }}"><i class="bi bi-layout-split me-2"></i>Gap Report</a>
                </div>
            </div>
        @endif

        <!-- Account -->
        <h6 class="nav-title">Account</h6>
        @if ($canViewActivity)
            <a href="{{ route('activity.index') }}" class="nav-link {{ request()->routeIs('activity.index') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i>
                <span>Activity History</span>
            </a>
        @endif
        @if ($canManageUsers)
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>User Management</span>
            </a>
        @endif
        <a href="{{ route('profile.index') }}" class="nav-link {{ request()->routeIs('profile.index') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link text-danger border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</nav>
