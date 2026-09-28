<?php

namespace Database\Seeders;

use App\Actions\RegraftBatch;
use App\Models\ActivityTask;
use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development-only reference data for the revised 15-entity model.
 *
 * These records must not be confused with real production data. The batch B001
 * deliberately carries genuine mortality AND a Phase 7B regraft into the linked
 * sub-batch B001-R1 so the Gap Report, Regraft page and Stock Ledger have real
 * non-zero data to verify against. Seeding respects the application's own
 * invariants: a regraft is seeded **only** through {@see RegraftBatch} (never a
 * raw insert), and seeded Stock Out quantities never exceed the referenced
 * batch's `available_count`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@cacaotrack.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => Role::ADMINISTRATOR,
                'permissions' => Role::defaultPermissionsFor(Role::ADMINISTRATOR),
                'email_verified_at' => now(),
            ],
        );

        $tasks = [
            ['task_name' => 'Inspect seedlings in Block A', 'description' => 'Walk Block A and record any wilting or pest damage.', 'priority' => 'high'],
            ['task_name' => 'Check water system pressure', 'description' => 'Verify irrigation pressure and inspect for leaks.', 'priority' => 'high'],
            ['task_name' => 'Inspect recent grafts for fungus', 'description' => 'Check newly grafted stock for fungal infection.', 'priority' => 'medium'],
            ['task_name' => 'Clean nursery paths', 'description' => 'Remove debris and weeds along nursery walkways.', 'priority' => 'low'],
        ];

        foreach ($tasks as $task) {
            ActivityTask::updateOrCreate(['task_name' => $task['task_name']], $task);
        }

        // Scion Variety: the single retained scion module (replaces both the old
        // `varieties` master and the generic `scions` stock pool).
        $batangas = ScionVariety::firstOrCreate(
            ['scion_variety_name' => 'Batangas'],
            ['description' => 'Common local scion variety used in nursery grafting.'],
        );
        $toledo = ScionVariety::firstOrCreate(
            ['scion_variety_name' => 'Toledo'],
            ['description' => 'High-yielding scion variety used for regrafting.'],
        );
        $criollo = ScionVariety::firstOrCreate(
            ['scion_variety_name' => 'Criollo'],
            ['description' => 'Fine-flavor cacao variety, relatively disease-sensitive.'],
        );
        $trinitario = ScionVariety::firstOrCreate(
            ['scion_variety_name' => 'Trinitario'],
            ['description' => 'Hybrid of Criollo and Forastero, good disease resistance.'],
        );
        $forastero = ScionVariety::firstOrCreate(
            ['scion_variety_name' => 'Forastero'],
            ['description' => 'Hardy high-yield variety, widely cultivated.'],
        );

        $suppliers = [
            ['supplier_name' => 'Davao Cacao Growers', 'contact_number' => '0917-111-2233', 'address' => 'Calinan, Davao City'],
            ['supplier_name' => 'Bukidnon Seedling Supply', 'contact_number' => '0918-444-5566', 'address' => 'Malaybalay, Bukidnon'],
            ['supplier_name' => 'North Mindanao Nursery', 'contact_number' => '0920-777-8899', 'address' => 'Cagayan de Oro, Misamis Oriental'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['supplier_name' => $supplier['supplier_name']], $supplier);
        }

        $clients = [
            ['client_name' => 'Cacao Farmers Coop', 'contact_number' => '0922-111-2222', 'address' => 'Compostela Valley, Davao de Oro'],
            ['client_name' => 'Mountain View Estate', 'contact_number' => '0923-333-4444', 'address' => 'Marilog, Davao City'],
            ['client_name' => 'Green Pod Traders', 'contact_number' => '0925-555-6666', 'address' => 'Valencia City, Bukidnon'],
        ];

        foreach ($clients as $client) {
            Client::firstOrCreate(['client_name' => $client['client_name']], $client);
        }

        $batches = [
            [
                'scion_variety_id' => $batangas->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B001',
                'total_grafted' => 500,
                'regraft' => 0,
                'available_count' => 500,
                'grafted_date' => '2026-07-15',
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ],
            [
                'scion_variety_id' => $trinitario->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B002',
                'total_grafted' => 150,
                'regraft' => 0,
                'available_count' => 150,
                'grafted_date' => '2026-08-01',
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ],
            [
                'scion_variety_id' => $forastero->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B003',
                'total_grafted' => 100,
                'regraft' => 0,
                'available_count' => 100,
                'grafted_date' => '2026-08-20',
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ],
        ];

        $batchModels = [];

        foreach ($batches as $batch) {
            $batchModels[$batch['batch_code']] = Batch::firstOrCreate(
                ['batch_code' => $batch['batch_code']],
                $batch,
            );
        }

        $b001 = $batchModels['B001'];
        $b002 = $batchModels['B002'];
        $b003 = $batchModels['B003'];

        // Genuine mortality for the seeded batches (Gap Report fixture). B001's
        // 25 deaths precede its regraft, so the Phase 7B regraftable cap
        // (`min(available, total_grafted - mortality)`) is exercised for real.
        $mortalityRecords = [
            ['batch_id' => $b001->batch_id, 'cause' => 'Disease', 'quantity' => 25, 'recorded_at' => '2026-09-01'],
            ['batch_id' => $b002->batch_id, 'cause' => 'Drought', 'quantity' => 15, 'recorded_at' => '2026-09-01'],
            ['batch_id' => $b003->batch_id, 'cause' => 'Pest', 'quantity' => 10, 'recorded_at' => '2026-08-28'],
        ];

        foreach ($mortalityRecords as $record) {
            MortalityRecord::firstOrCreate(
                [
                    'batch_id' => $record['batch_id'],
                    'cause' => $record['cause'],
                    'recorded_at' => $record['recorded_at'].' 00:00:00',
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => $record['quantity'],
                ],
            );
        }

        // Phase 7B regraft scenario — seeded ONLY through the RegraftBatch action
        // (never a raw insert): 100 plants move out of B001 Batangas into the
        // linked sub-batch B001-R1 using Toledo. Guarded so re-running the
        // seeder is idempotent instead of creating B001-R2.
        if ((int) $b001->fresh()->regraft < 100) {
            (new RegraftBatch)->execute(
                $b001,
                100,
                (int) $toledo->scion_variety_id,
                '2026-09-05',
                'Regrafted disease-affected stock into a Toledo sub-batch.',
                (int) $admin->id,
            );
        }

        $b001r1 = Batch::where('batch_code', 'B001-R1')->first();

        if ($b001r1 !== null) {
            // Mortality on the sub-batch (its own independent 21-day cycle).
            MortalityRecord::firstOrCreate(
                [
                    'batch_id' => $b001r1->batch_id,
                    'cause' => 'Disease',
                    'recorded_at' => '2026-09-10 00:00:00',
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => 5,
                ],
            );
        }

        // Phase 7C nested scenario — a sub-batch is itself regrafted: 30 plants
        // move out of B001-R1 into B001-R1-R1 using a third variety (Criollo).
        // Seeded ONLY through the RegraftBatch action, guarded for idempotency.
        $b001r1r1 = null;

        if ($b001r1 !== null && (int) $b001r1->fresh()->regraft < 30) {
            $b001r1r1 = (new RegraftBatch)->execute(
                $b001r1,
                30,
                (int) $criollo->scion_variety_id,
                '2026-09-08',
                'Regrafted a further 30 plants from B001-R1 into a linked sub-batch.',
                (int) $admin->id,
            );
        }

        $b001r1r1 ??= Batch::where('batch_code', 'B001-R1-R1')->first();

        // Stock In has no batch_id: it records the received quantity before a
        // batch is established. Scion_Receiving was merged into Stock_In
        // (client-confirmed same real-world event), so scion receipts are seeded
        // here too: a row with a `scion_variety_id` includes scion; a seedling
        // row leaves it null.
        $stockIns = [
            ['supplier_name' => 'Davao Cacao Growers', 'quantity' => 500, 'origin' => 'Davao del Sur', 'date_received' => '2026-07-01'],
            ['supplier_name' => 'Bukidnon Seedling Supply', 'quantity' => 300, 'origin' => 'Bukidnon', 'date_received' => '2026-08-01'],
            ['scion_variety_name' => 'Criollo', 'supplier_name' => 'Davao Cacao Growers', 'quantity' => 120, 'unit_price' => 12.50, 'origin' => 'Davao del Sur', 'notes' => 'Premium Criollo scion bundle.', 'date_received' => '2026-08-15'],
            ['scion_variety_name' => 'Trinitario', 'supplier_name' => null, 'quantity' => 80, 'unit_price' => null, 'origin' => null, 'notes' => null, 'date_received' => '2026-08-20'],
        ];

        foreach ($stockIns as $stockIn) {
            $isScion = isset($stockIn['scion_variety_name']);

            $supplierId = $stockIn['supplier_name'] === null
                ? null
                : Supplier::where('supplier_name', $stockIn['supplier_name'])->value('supplier_id');

            $scionVarietyId = $isScion
                ? ScionVariety::where('scion_variety_name', $stockIn['scion_variety_name'])->value('scion_variety_id')
                : null;

            $keys = $isScion
                ? ['scion_variety_id' => $scionVarietyId, 'date_received' => $stockIn['date_received']]
                : ['supplier_id' => $supplierId, 'date_received' => $stockIn['date_received']];

            StockIn::firstOrCreate($keys, [
                'supplier_id' => $supplierId,
                'scion_variety_id' => $scionVarietyId,
                'user_id' => $admin->id,
                'quantity' => $stockIn['quantity'],
                'unit_price' => $stockIn['unit_price'] ?? null,
                'origin' => $stockIn['origin'] ?? '',
                'notes' => $stockIn['notes'] ?? null,
            ]);
        }

        // Stock Out: header + one detail line. Phase 7B scenario — the release
        // is taken from the regraft sub-batch B001-R1 (seedlings move out of the
        // sub-batch, not the parent). The released quantity is checked against
        // the live available balance, then the balance is decremented exactly as
        // the application does.
        $client = Client::where('client_name', 'Cacao Farmers Coop')->first();

        if ($b001r1 !== null) {
            $header = StockOut::firstOrCreate(
                [
                    'client_id' => $client->client_id,
                    'release_date' => '2026-09-12',
                ],
                [
                    'user_id' => $admin->id,
                    'total_amount' => null,
                ],
            );

            if ($header->wasRecentlyCreated) {
                $releasedFromSubBatch = 20;

                if ($releasedFromSubBatch > (int) $b001r1->available_count) {
                    $releasedFromSubBatch = (int) $b001r1->available_count;
                }

                $header->details()->create([
                    'batch_id' => $b001r1->batch_id,
                    'quantity' => $releasedFromSubBatch,
                    'unit' => 'seedlings',
                    'unit_price' => 25.00,
                    'amount' => $releasedFromSubBatch * 25.00,
                ]);

                $header->forceFill(['total_amount' => $releasedFromSubBatch * 25.00])->save();
                $b001r1->decrement('available_count', $releasedFromSubBatch);
            }
        }

        // Monthly inventory snapshots (current month) for the seeded batches,
        // including both regraft sub-batches.
        $b002->refresh();
        $b001r1?->refresh();
        $b001r1r1?->refresh();

        $inventoryBatches = array_filter([$b001->refresh(), $b002, $b003, $b001r1, $b001r1r1]);

        foreach ($inventoryBatches as $batch) {
            $month = now()->startOfMonth()->toDateString();

            if (Inventory::where('batch_id', $batch->batch_id)
                ->whereYear('inventory_date', now()->year)
                ->whereMonth('inventory_date', now()->month)
                ->exists()) {
                continue;
            }

            Inventory::create([
                'batch_id' => $batch->batch_id,
                'inventory_date' => $month,
                'total_grafted' => (int) $batch->total_grafted,
                'regraft' => $batch->totalReplanted(),
                'mortality' => $batch->effectiveMortalityQuantity(),
                'last_update' => now(),
            ]);
        }

        // Scion Receiving: removed. Scion receipts are now seeded as
        // scion-inclusive Stock In rows above (see the Stock In block); the
        // standalone scion_receivings table was retired in the merge.

        $this->call(ActivityLogSeeder::class);
    }
}
