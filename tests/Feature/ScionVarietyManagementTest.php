<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-07 Scion Variety master data. Owner-directed: the Archived tab was removed
 * and archive/restore replaced by a guarded permanent Delete (a variety with
 * references is refused so operational history is never destroyed).
 */
class ScionVarietyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_scion_varieties_to_login(): void
    {
        $this->get(route('operational.scion-varieties'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_scion_varieties_page(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        ScionVariety::create(['scion_variety_name' => 'Criollo']);

        $this->actingAs($user)
            ->get(route('operational.scion-varieties'))
            ->assertOk()
            ->assertSee('Criollo')
            ->assertDontSee('Archived');
    }

    public function test_unreferenced_scion_variety_can_be_deleted(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Temp']);

        $this->actingAs($user)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_SCION_VARIETY_DELETED,
            'user_id' => $user->id,
        ]);
    }

    public function test_scion_variety_used_by_a_batch_cannot_be_deleted(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'In use']);

        Batch::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('error', 'Scion variety In use cannot be deleted because it has batch assignments. Its history must be preserved.');

        $this->assertDatabaseHas('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_SCION_VARIETY_DELETED)->count());
    }

    public function test_scion_variety_used_by_a_stock_in_cannot_be_deleted(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Received']);

        StockIn::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'unit_price' => 12.50,
        ]);

        $this->actingAs($user)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('error', 'Scion variety Received cannot be deleted because it has stock-in receipts. Its history must be preserved.');

        $this->assertDatabaseHas('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_SCION_VARIETY_DELETED)->count());
    }

    public function test_scion_variety_used_by_a_stock_out_detail_cannot_be_deleted(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Released']);

        $batch = Batch::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
        ]);

        StockOutDetail::factory()->create(['batch_id' => $batch->batch_id]);

        Batch::query()
            ->whereKey($batch->batch_id)
            ->update(['scion_variety_id' => null]);

        $this->actingAs($user)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('error', 'Scion variety Released cannot be deleted because it has stock-out lines. Its history must be preserved.');

        $this->assertDatabaseHas('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_SCION_VARIETY_DELETED)->count());
    }

    public function test_listing_shows_the_batch_count_and_the_guarded_delete_state(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $used = ScionVariety::create(['scion_variety_name' => 'Used']);
        $unused = ScionVariety::create(['scion_variety_name' => 'Unused']);

        Batch::factory()->count(2)->create([
            'scion_variety_id' => $used->scion_variety_id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('operational.scion-varieties'))
            ->assertOk()
            ->assertSee('Used')
            ->assertSee('Unused')
            ->assertSee('<span class="badge bg-success">2</span>', false)
            ->assertSee('<span class="badge bg-secondary">0</span>', false)
            ->assertSee('This scion variety has dependent records (batch assignments) and cannot be deleted.', false)
            ->assertSee('data-bs-target="#deleteScionVarietyModal"', false);
    }

    public function test_listing_does_not_grow_its_query_count_with_the_number_of_varieties(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $referencedVariety = function (string $name) use ($user): void {
            $variety = ScionVariety::create(['scion_variety_name' => $name]);

            Batch::factory()->create([
                'scion_variety_id' => $variety->scion_variety_id,
                'user_id' => $user->id,
            ]);
        };

        $referencedVariety('First');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('operational.scion-varieties'))->assertOk();
        $baselineQueries = count(DB::getQueryLog());

        $referencedVariety('Second');
        $referencedVariety('Third');

        DB::flushQueryLog();
        $this->actingAs($user)
            ->get(route('operational.scion-varieties'))
            ->assertOk()
            ->assertSee('Second')
            ->assertSee('Third');
        $expandedQueries = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($baselineQueries, $expandedQueries);
    }
}
