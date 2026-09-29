<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makePopulation(User $user, int $grafted = 200, int $available = 180, int $mortality = 20): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo', 'description' => 'Test variety']);

        $batch = Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $grafted,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        if ($mortality > 0) {
            MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => $user->id,
                'quantity' => $mortality,
                'cause' => 'Disease',
                'recorded_at' => now(),
            ]);
        }

        return $batch;
    }

    public function test_guests_are_redirected_from_the_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Total Active Inventory');
        $response->assertSee('Crude Mortality Rate');
        $response->assertSee('Regraft Deficit');
        $response->assertSee('Recent Activities');
    }

    public function test_dashboard_kpis_come_from_the_centralized_formulas(): void
    {
        $user = User::factory()->create();
        $this->makePopulation($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalActiveInventory', AnalyticsMetrics::totalActiveInventory());
        $response->assertViewHas('crudeMortalityRate', AnalyticsMetrics::crudeMortalityRate());
        $response->assertViewHas('totalRegraftDeficit', AnalyticsMetrics::totalRegraftDeficit());

        // Real rendered values: 180 active, 20/200 = 10.00%, deficit 20 - 0 = 20.
        $response->assertSee('180');
        $response->assertSee('10.00%');
        $response->assertSee('20');
    }

    public function test_dashboard_charts_render_with_real_data(): void
    {
        $user = User::factory()->create();
        $this->makePopulation($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('mortalityTrendChart');
        $response->assertSee('survivalChart');
        $response->assertSee('inventoryVarietyChart');
        $response->assertViewHas('mortalityTrend', fn (array $trend) => $trend['total'] === 20);
        $response->assertViewHas('inventoryByScionVariety', fn (array $data) => $data['values'] === [180]);
        // surviving = total grafted 200 - recorded mortality 20
        $response->assertViewHas('survivalVsMortality', fn (array $data) => $data['surviving'] === 180 && $data['mortality'] === 20);
    }

    public function test_dashboard_shows_empty_states_and_no_fabricated_charts_when_there_is_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No data available for the selected period.');
        $response->assertSee('No activities have been recorded yet.');
        $response->assertDontSee('mortalityTrendChart');
        $response->assertDontSee('survivalChart');
        $response->assertDontSee('inventoryVarietyChart');
        $response->assertViewHas('totalActiveInventory', 0);
        $response->assertViewHas('totalRegraftDeficit', 0);
    }

    public function test_dashboard_does_not_render_the_old_mock_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('12,450');
        $response->assertDontSee('8.4%');
        $response->assertDontSee('5,200');
        $response->assertDontSee('Supplier A');
    }

    public function test_dashboard_recent_activities_use_the_real_audit_trail_and_are_limited_to_five(): void
    {
        $user = User::factory()->create();
        $batch = $this->makePopulation($user);

        for ($i = 1; $i <= 7; $i++) {
            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_MORTALITY_RECORDED,
                "Activity {$i}",
                $user->id,
                now()->subMinutes($i),
            );
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Activity 1');
        $response->assertSee('Activity 5');
        $response->assertDontSee('Activity 6');
        $response->assertDontSee('Activity 7');
        $response->assertViewHas('recentActivities', fn ($activities) => $activities->count() === 5);
    }
}
