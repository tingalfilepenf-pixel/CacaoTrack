<?php

namespace Tests\Feature;

use App\Actions\RegraftBatch;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regraft page + request contract on the Phase 7B flow.
 *
 * Regraft and Replanting are the same recorded procedure. A regraft now moves a
 * quantity out of a source batch into a linked sub-batch via
 * {@see RegraftBatch} — the removed deficit-cap validation is gone.
 * The deeper transactional/atomicity/independence coverage lives in
 * {@see RegraftFlowTest}.
 */
class ReplantingTest extends TestCase
{
    use RefreshDatabase;

    private function variety(string $name = 'Batangas'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    private function batch(User $user, ScionVariety $variety, int $total = 100): Batch
    {
        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $total,
            'regraft' => 0,
            'available_count' => $total,
            'grafted_date' => now()->subMonths(2)->toDateString(),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_guests_are_redirected_from_replanting_to_login(): void
    {
        $this->get(route('operational.replanting'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_replanting_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.replanting'))
            ->assertOk()
            ->assertSee('Regraft Status by Batch')
            ->assertSee('Regraft History');
    }

    public function test_user_can_record_a_regraft_into_a_sub_batch(): void
    {
        $user = User::factory()->create();
        $toledo = $this->variety('Toledo');
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 20,
                'scion_variety_id' => $toledo->scion_variety_id,
                'replanting_date' => now()->subDays(3)->toDateString(),
                'remark' => 'Replaced affected seedlings.',
            ])
            ->assertRedirect(route('operational.replanting'))
            ->assertSessionHas('success');

        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $this->assertSame($source->batch_id, (int) $sub->parent_batch_id);
        $this->assertSame($toledo->scion_variety_id, (int) $sub->scion_variety_id);

        $this->assertDatabaseHas('replanting_records', [
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'quantity' => 20,
            'remark' => 'Replaced affected seedlings.',
        ]);
    }

    public function test_regraft_requires_a_positive_quantity(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 0,
                'scion_variety_id' => $source->scion_variety_id,
                'replanting_date' => now()->subDays(3)->toDateString(),
            ])
            ->assertSessionHasErrors('quantity', null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 0);
    }

    public function test_regraft_requires_a_valid_batch_variety_and_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => 999,
                'quantity' => 5,
                'scion_variety_id' => '',
                'replanting_date' => '',
            ])
            ->assertSessionHasErrors(['batch_id', 'scion_variety_id', 'replanting_date'], null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 0);
    }

    public function test_regraft_remark_is_optional(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)->post(route('operational.replanting.records.store'), [
            'batch_id' => $source->batch_id,
            'quantity' => 5,
            'scion_variety_id' => $source->scion_variety_id,
            'replanting_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertDatabaseHas('replanting_records', [
            'quantity' => 5,
            'remark' => null,
        ]);
    }

    public function test_replanting_page_filters_by_batch(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->get(route('operational.replanting', ['batch_id' => $source->batch_id]))
            ->assertOk()
            ->assertSee('B001');
    }

    public function test_batch_regraft_deficit_is_derived_from_the_counter(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 100);

        MortalityRecord::create([
            'batch_id' => $source->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => now()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($user)->post(route('operational.replanting.records.store'), [
            'batch_id' => $source->batch_id,
            'quantity' => 15,
            'scion_variety_id' => $source->scion_variety_id,
            'replanting_date' => now()->subDays(3)->toDateString(),
        ]);

        $source->refresh();
        $this->assertSame(25, $source->effectiveMortalityQuantity());
        $this->assertSame(15, $source->totalReplanted());
        $this->assertSame(10, $source->replantingDeficit());
        $this->assertSame(15, ReplantingRecord::totalRegraftedFromBatch($source->batch_id));
    }
}
