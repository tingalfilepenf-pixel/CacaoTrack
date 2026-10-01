<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\ScionVariety;
use App\Models\StockOut;
use App\Models\StockOutDetail;
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

    /**
     * Rule 5: `available_count == (total_grafted - regraft) - SUM(stock_out quantity)`.
     * A legitimate release passes; a balance silently altered to a value that is
     * still inside the valid range (0..total_grafted - regraft) but no longer
     * matches the recorded Stock Out is caught by the history reconciliation —
     * the range-only checks (rules 2/3) would not have caught it.
     */
    public function test_health_command_reconciles_available_count_against_the_stock_out_history(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);

        // A legitimate release of 30: header + one detail line, then the balance
        // is decremented exactly as the application does.
        $stockOut = StockOut::create([
            'client_id' => $client->client_id,
            'user_id' => $user->id,
            'release_date' => '2026-09-10',
            'total_amount' => 30 * 10.0,
        ]);

        $stockOut->details()->create([
            'batch_id' => $batch->batch_id,
            'quantity' => 30,
            'unit' => StockOutDetail::UNITS[0],
            'unit_price' => 10.0,
            'amount' => 30 * 10.0,
        ]);

        $batch->update(['available_count' => 70]);

        // Consistent history -> clean pass.
        $this->artisan('cacao:health')
            ->expectsOutputToContain('Cacao health check passed')
            ->assertExitCode(0);

        // Corrupt the balance to a value that is STILL within the valid range
        // (0 <= 80 <= total_grafted - regraft = 100) but no longer matches the
        // recorded Stock Out (which requires 70). Bypass the model so no
        // application rule corrects it.
        DB::table('batches')
            ->where('batch_id', $batch->batch_id)
            ->update(['available_count' => 80]);

        $this->artisan('cacao:health')
            ->expectsOutputToContain('B001')
            ->expectsOutputToContain('Cacao health check FAILED')
            ->assertExitCode(1);
    }
}
