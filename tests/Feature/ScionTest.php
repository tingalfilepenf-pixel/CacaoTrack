<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\PotType;
use App\Models\ReplantingRecord;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use App\Support\ScionMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScionTest extends TestCase
{
    use RefreshDatabase;

    private function createScion(int $quantity = 0, string $name = 'Scion'): Scion
    {
        return Scion::create([
            'scion_name' => $name,
            'quantity' => $quantity,
        ]);
    }

    private function createBatch(User $user, Scion $scion, string $code = 'B001'): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'viable_count' => 100,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_guests_are_redirected_from_scions_to_login(): void
    {
        $this->get(route('inventory.scions'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_scions_page(): void
    {
        $user = User::factory()->create();
        $this->createScion(75);

        $this->actingAs($user)
            ->get(route('inventory.scions'))
            ->assertOk()
            ->assertSee('Scion Stock')
            ->assertSee('Number of Scions')
            ->assertSee('Receive Scions')
            ->assertSee('Re-grafted Scions')
            ->assertSee('Rejected Scions');
    }

    public function test_receive_scions_credits_the_pool(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(0);

        $this->actingAs($user)
            ->post(route('inventory.scions.receive'), ['quantity' => 50])
            ->assertRedirect(route('inventory.scions'))
            ->assertSessionHas('success');

        $this->assertSame(50, $scion->fresh()->quantity);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'scion_stock_received',
            'user_id' => $user->id,
        ]);
    }

    public function test_receive_scions_accumulates_the_balance(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(40);

        $this->actingAs($user)->post(route('inventory.scions.receive'), ['quantity' => 60]);

        $this->assertSame(100, $scion->fresh()->quantity);
    }

    public function test_receive_scions_creates_the_pool_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.scions.receive'), ['quantity' => 25])
            ->assertRedirect(route('inventory.scions'));

        $this->assertSame(1, Scion::count());
        $this->assertSame(25, (int) Scion::first()->quantity);
    }

    public function test_receive_scions_requires_a_positive_quantity(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(10);

        $this->actingAs($user)
            ->from(route('inventory.scions'))
            ->post(route('inventory.scions.receive'), ['quantity' => 0])
            ->assertSessionHasErrors(['quantity'], null, 'scion');

        $this->actingAs($user)
            ->from(route('inventory.scions'))
            ->post(route('inventory.scions.receive'), ['quantity' => -5])
            ->assertSessionHasErrors(['quantity'], null, 'scion');

        $this->actingAs($user)
            ->from(route('inventory.scions'))
            ->post(route('inventory.scions.receive'), [])
            ->assertSessionHasErrors(['quantity'], null, 'scion');

        $this->assertSame(10, $scion->fresh()->quantity);
    }

    public function test_scion_page_shows_the_available_balance(): void
    {
        $user = User::factory()->create();
        $this->createScion(321);

        $this->actingAs($user)
            ->get(route('inventory.scions'))
            ->assertOk()
            ->assertSee('321');
    }

    public function test_replanting_deducts_from_the_available_scions(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(100);
        $batch = $this->createBatch($user, $scion);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'replanting_date' => '2026-09-03',
            ])
            ->assertRedirect(route('operational.replanting'))
            ->assertSessionHas('success');

        $this->assertSame(80, $scion->fresh()->quantity);
        $this->assertSame(20, ReplantingRecord::totalForBatch($batch->batch_id));
    }

    public function test_replanting_is_rejected_when_scions_are_insufficient(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(5);
        $batch = $this->createBatch($user, $scion);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'replanting_date' => '2026-09-03',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('replanting_records', 0);
        $this->assertSame(5, $scion->fresh()->quantity);
    }

    public function test_scion_metrics_use_the_stored_balance(): void
    {
        $user = User::factory()->create();
        $scion = $this->createScion(80);
        $batch = $this->createBatch($user, $scion);

        ReplantingRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'replanting_date' => '2026-09-02',
        ]);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-03 08:00:00',
        ]);

        $metrics = ScionMetrics::forScions([$scion->scion_id]);

        $this->assertSame(80, $metrics['available']);
        $this->assertSame(20, $metrics['regrafted']);
        $this->assertSame(30, $metrics['rejected']);
        $this->assertSame(100, $metrics['received']);
    }

    public function test_scion_metrics_are_zero_without_scions(): void
    {
        $this->assertSame(
            ['received' => 0, 'regrafted' => 0, 'rejected' => 0, 'available' => 0],
            ScionMetrics::forScions([]),
        );
    }

    public function test_scion_reference_is_formatted(): void
    {
        $first = new Scion;
        $first->scion_id = 1;

        $padded = new Scion;
        $padded->scion_id = 42;

        $large = new Scion;
        $large->scion_id = 1234;

        $this->assertSame('SCN-001', $first->reference());
        $this->assertSame('SCN-042', $padded->reference());
        $this->assertSame('SCN-1234', $large->reference());
    }
}
