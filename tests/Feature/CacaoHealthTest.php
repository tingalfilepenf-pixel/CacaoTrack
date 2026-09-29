<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read-only `cacao:health` command reports any batch that violates a
 * quantity invariant. The deliberately-broken fixture is written with a direct
 * DB update so the corruption bypasses the model and its application rules.
 */
class CacaoHealthTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, string $code = 'B001', int $total = 100): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $total,
            'regraft' => 0,
            'available_count' => $total,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_health_command_passes_on_a_consistent_dataset(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100);

        $this->artisan('cacao:health')
            ->expectsOutputToContain('Cacao health check passed')
            ->assertExitCode(0);
    }

    public function test_health_command_reports_a_deliberately_broken_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'BROKEN', 100);

        // Bypass the model and corrupt the live balance directly in the DB so it
        // exceeds the batch's surviving population (total_grafted - regraft).
        DB::table('batches')
            ->where('batch_id', $batch->batch_id)
            ->update(['available_count' => 150]);

        // One substring expectation per distinct output line (the batch code is
        // on the violation row; the FAILED summary is its own line).
        $this->artisan('cacao:health')
            ->expectsOutputToContain('BROKEN')
            ->expectsOutputToContain('Cacao health check FAILED')
            ->assertExitCode(1);
    }
}
