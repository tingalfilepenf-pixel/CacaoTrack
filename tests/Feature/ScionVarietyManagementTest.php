<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSessionHas('error');

        $this->assertDatabaseHas('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_SCION_VARIETY_DELETED)->count());
    }
}
