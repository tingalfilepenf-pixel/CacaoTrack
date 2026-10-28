<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Operational dashboard (PRD §12 / §24).
     *
     * Every KPI and chart value is derived from actual database rows through
     * the centralized definitions in AnalyticsMetrics (RULE 5/6/12) — nothing
     * is hardcoded. The same formulas are shared with the Operational Analytics
     * page so a metric never has two definitions (PRD §25).
     *
     * Analytics cards, charts and the Activity History panel are only rendered
     * for holders of the matching access restriction; Quick Actions are built
     * from the same catalog so the dashboard never offers a dead link.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAnalytics = $user?->hasPermission('view_analytics') ?? false;
        $canViewActivity = $user?->hasPermission('view_activity') ?? false;

        return view('dashboard.index', [
            'canViewAnalytics' => $canViewAnalytics,
            'canViewActivity' => $canViewActivity,
            'quickActions' => $this->quickActions($user),
            'totalGrafted' => $canViewAnalytics ? AnalyticsMetrics::totalGrafted() : 0,
            'totalActiveInventory' => $canViewAnalytics ? AnalyticsMetrics::totalActiveInventory() : 0,
            'crudeMortalityRate' => $canViewAnalytics ? AnalyticsMetrics::crudeMortalityRate() : 0,
            'mortalityTrend' => $canViewAnalytics ? AnalyticsMetrics::mortalityTrend(6) : ['labels' => [], 'values' => [], 'total' => 0],
            'inventoryByScionVariety' => $canViewAnalytics ? AnalyticsMetrics::inventoryByScionVariety() : ['labels' => [], 'values' => []],
            'survivalVsMortality' => $canViewAnalytics ? AnalyticsMetrics::survivalVsMortality() : ['totalGrafted' => 0, 'surviving' => 0, 'mortality' => 0],
            'recentActivities' => $canViewActivity
                ? ActivityLog::with(['user', 'batch'])
                    ->orderByDesc('activity_date')
                    ->limit(5)
                    ->get()
                : collect(),
        ]);
    }

    /**
     * Permission-gated shortcuts to the real feature routes.
     *
     * @return list<array{label: string, route: string, icon: string, class: string}>
     */
    private function quickActions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $actions = [];

        if ($user->hasPermission('record_operations')) {
            $actions[] = ['label' => 'Record Mortality', 'route' => 'operational.mortality', 'icon' => 'bi-clipboard-pulse', 'class' => 'btn-outline-danger'];
            $actions[] = ['label' => 'Record Regraft', 'route' => 'operational.regraft', 'icon' => 'bi-arrow-repeat', 'class' => 'btn-outline-success'];
        }

        if ($user->hasPermission('manage_stock')) {
            $actions[] = ['label' => 'Stock In', 'route' => 'inventory.stock-in', 'icon' => 'bi-box-arrow-in-right', 'class' => 'btn-outline-primary'];
            $actions[] = ['label' => 'Stock Out', 'route' => 'inventory.stock-out', 'icon' => 'bi-box-arrow-right', 'class' => 'btn-outline-secondary'];
        }

        if ($user->hasPermission('manage_checklist') || $user->hasPermission('complete_checklist')) {
            $actions[] = ['label' => 'Daily Checklist', 'route' => 'operational.checklist', 'icon' => 'bi-check-square', 'class' => 'btn-outline-success'];
        }

        if ($user->hasPermission('manage_inventory')) {
            $actions[] = ['label' => 'Scion Varieties', 'route' => 'operational.scion-varieties', 'icon' => 'bi-tags', 'class' => 'btn-outline-primary'];
            $actions[] = ['label' => 'Clients', 'route' => 'inventory.clients', 'icon' => 'bi-people', 'class' => 'btn-outline-secondary'];
            $actions[] = ['label' => 'Suppliers', 'route' => 'inventory.suppliers', 'icon' => 'bi-truck', 'class' => 'btn-outline-secondary'];
        }

        if ($user->hasPermission('view_analytics')) {
            $actions[] = ['label' => 'Reports', 'route' => 'analytics.index', 'icon' => 'bi-graph-up', 'class' => 'btn-outline-dark'];
        }

        return $actions;
    }
}
