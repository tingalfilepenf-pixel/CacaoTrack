<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\AnalyticsMetrics;

class DashboardController extends Controller
{
    /**
     * Operational dashboard (PRD §12 / §24).
     *
     * Every KPI and chart value is derived from actual database rows through
     * the centralized definitions in AnalyticsMetrics (RULE 5/6/12) — nothing
     * is hardcoded. The same formulas are shared with the Operational Analytics
     * page so a metric never has two definitions (PRD §25).
     */
    public function index()
    {
        return view('dashboard.index', [
            'totalActiveInventory' => AnalyticsMetrics::totalActiveInventory(),
            'crudeMortalityRate' => AnalyticsMetrics::crudeMortalityRate(),
            'totalRegraftDeficit' => AnalyticsMetrics::totalRegraftDeficit(),
            'mortalityTrend' => AnalyticsMetrics::mortalityTrend(6),
            'inventoryByScionVariety' => AnalyticsMetrics::inventoryByScionVariety(),
            'survivalVsMortality' => AnalyticsMetrics::survivalVsMortality(),
            'recentActivities' => ActivityLog::with(['user', 'batch'])
                ->orderByDesc('activity_date')
                ->limit(5)
                ->get(),
        ]);
    }
}
