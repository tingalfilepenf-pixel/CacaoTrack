<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\StockBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockBalancesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function stockIn(int $quantity, ?int $varietyId = null, ?int $userId = null): StockIn
    {
        return StockIn::create([
            'supplier_id' => null,
            'scion_variety_id' => $varietyId,
            'user_id' => $userId ?? $this->user()->id,
            'quantity' => $quantity,
            'unit_price' => $varietyId ? 10.00 : null,
            'origin' => 'Test Origin',
            'date_received' => '2026-11-04',
        ]);
    }

    private function batch(
        string $code,
        int $total = 100,
        ?int $seedlingsUsed = null,
        ?int $scionUsed = null,
        ?int $varietyId = null,
        string $source = Batch::SOURCE_OWN,
        ?int $supplierId = null,
        ?int $parentId = null,
    ): Batch {
        return Batch::create([
            'source' => $source,
            'supplier_id' => $supplierId,
            'scion_variety_id' => $varietyId,
            'parent_batch_id' => $parentId,
            'user_id' => $this->user()->id,
            'batch_code' => $code,
            'total_grafted' => $total,
            'seedlings_used' => $seedlingsUsed,
            'scion_used' => $scionUsed,
            'regraft' => 0,
            'available_count' => $total,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_seedlings_available_is_received_minus_only_tracked_own_batches(): void
    {
        $this->stockIn(100);
        $this->stockIn(50);
        $this->stockIn(25, ScionVariety::create(['scion_variety_name' => 'Scion'])->scion_variety_id);

        $this->batch('B001', 100); // legacy, used null: not deducted
        $this->batch('B002', 40, seedlingsUsed: 40); // tracked: deducted
        $this->batch(
            'S001',
            30,
            source: Batch::SOURCE_SUPPLIER,
            supplierId: Supplier::create([
                'supplier_name' => 'Supplier A',
                'contact_number' => '0917-000-0000',
                'address' => 'Test Address',
            ])->supplier_id,
        ); // supplier delivery: not deducted

        $this->assertSame(150, StockBalances::seedlingsReceived());
        $this->assertSame(40, StockBalances::seedlingsUsed());
        $this->assertSame(110, StockBalances::seedlingsAvailable());
        $this->assertSame(-10, StockBalances::seedlingsBalance(30, 40));
        $this->assertSame(0, StockBalances::seedlingsAvailable(30, 40));
    }

    public function test_scion_availability_subtracts_batch_use_and_regraft_use(): void
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Batangas']);
        $this->stockIn(100, $variety->scion_variety_id);

        $parent = $this->batch('B001', 40, scionUsed: 40, varietyId: $variety->scion_variety_id);
        $this->batch(
            'S001',
            30,
            varietyId: $variety->scion_variety_id,
            source: Batch::SOURCE_SUPPLIER,
            supplierId: Supplier::create([
                'supplier_name' => 'Supplier A',
                'contact_number' => '0917-000-0000',
                'address' => 'Test Address',
            ])->supplier_id,
        );
        $subBatch = $this->batch('B001-R1', 20, varietyId: $variety->scion_variety_id, parentId: $parent->batch_id);

        RegraftRecord::create([
            'batch_id' => $subBatch->batch_id,
            'parent_batch_id' => $parent->batch_id,
            'user_id' => $this->user()->id,
            'quantity' => 20,
            'regraft_date' => '2026-09-10',
            'remark' => null,
        ]);

        $fresh = $variety->fresh();

        $this->assertSame(40, $fresh->batchScionUsedQuantity());
        $this->assertSame(20, $fresh->regraftScionUsedQuantity());
        $this->assertSame(60, $fresh->usedScionQuantity());
        $this->assertSame(100, $fresh->receivedScionQuantity());
        $this->assertSame(40, $fresh->scionBalance());
        $this->assertSame(40, $fresh->availableScionQuantity());

        $eager = ScionVariety::query()->withScionAvailability()->findOrFail($variety->scion_variety_id);
        $this->assertSame(40, $eager->availableScionQuantity());
    }

    public function test_legacy_batches_without_tracked_use_keep_scion_availability_unchanged(): void
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Toledo']);
        $this->stockIn(50, $variety->scion_variety_id);

        $this->batch('B001', 50, varietyId: $variety->scion_variety_id);

        $this->assertSame(0, $variety->fresh()->usedScionQuantity());
        $this->assertSame(50, $variety->fresh()->availableScionQuantity());
    }

    public function test_the_seedling_pool_lock_selects_only_seedling_rows_in_ascending_order(): void
    {
        $seedlingA = $this->stockIn(10);
        $this->stockIn(20, ScionVariety::create(['scion_variety_name' => 'Scion'])->scion_variety_id);
        $seedlingB = $this->stockIn(30);

        $locked = DB::transaction(fn () => StockBalances::lockSeedlingPool());

        $this->assertSame(
            [$seedlingA->stock_in_id, $seedlingB->stock_in_id],
            $locked->pluck('stock_in_id')->map(fn ($id) => (int) $id)->all(),
        );
    }
}
