<?php

namespace Database\Seeders;

use App\Models\ActivityTask;
use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\Role;
use App\Models\ScionReceiving;
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
 * deliberately carries genuine mortality AND regraft (Replanting) records so
 * the Gap Report has real non-zero data to verify against. Seeding respects the
 * application's own invariants: seeded Stock Out quantities never exceed the
 * referenced batch's `available_count`, and orders/receipts are applied through
 * the same balance the app maintains.
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
                'scion_variety_id' => $criollo->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B001',
                'total_grafted' => 200,
                'viable_count' => 180,
                'available_count' => 180,
                'grafted_date' => '2026-07-15',
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ],
            [
                'scion_variety_id' => $trinitario->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B002',
                'total_grafted' => 150,
                'viable_count' => 140,
                'available_count' => 140,
                'grafted_date' => '2026-08-01',
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ],
            [
                'scion_variety_id' => $forastero->scion_variety_id,
                'user_id' => $admin->id,
                'batch_code' => 'B003',
                'total_grafted' => 100,
                'viable_count' => 95,
                'available_count' => 95,
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

        // Genuine mortality + regraft for B001 (Gap Report fixture), plus
        // incremental entries for the other batches (matches the notebook-style
        // cumulative recording the client uses).
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

        $replantingRecords = [
            ['batch_id' => $b001->batch_id, 'quantity' => 15, 'replanting_date' => '2026-09-03', 'remark' => 'Replaced disease-affected seedlings.'],
            ['batch_id' => $b002->batch_id, 'quantity' => 15, 'replanting_date' => '2026-09-03', 'remark' => 'Full regraft of drought losses.'],
            ['batch_id' => $b003->batch_id, 'quantity' => 4, 'replanting_date' => '2026-09-04', 'remark' => 'Partial regraft; remaining stock pending.'],
        ];

        foreach ($replantingRecords as $record) {
            ReplantingRecord::firstOrCreate(
                [
                    'batch_id' => $record['batch_id'],
                    'replanting_date' => $record['replanting_date'],
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => $record['quantity'],
                    'remark' => $record['remark'],
                ],
            );
        }

        // Stock In has no batch_id: it records the received vs viable count
        // before a batch is established.
        $stockIns = [
            ['supplier_name' => 'Davao Cacao Growers', 'quantity' => 500, 'viable' => 480, 'origin' => 'Davao del Sur', 'date_received' => '2026-07-01'],
            ['supplier_name' => 'Bukidnon Seedling Supply', 'quantity' => 300, 'viable' => 300, 'origin' => 'Bukidnon', 'date_received' => '2026-08-01'],
        ];

        foreach ($stockIns as $stockIn) {
            $supplier = Supplier::where('supplier_name', $stockIn['supplier_name'])->first();

            StockIn::firstOrCreate(
                [
                    'supplier_id' => $supplier->supplier_id,
                    'date_received' => $stockIn['date_received'],
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => $stockIn['quantity'],
                    'viable' => $stockIn['viable'],
                    'origin' => $stockIn['origin'],
                ],
            );
        }

        // Stock Out: header + one detail line per batch/scion variety. The
        // released quantity is checked against the live available balance, then
        // the balance is decremented exactly as the application does.
        $client = Client::where('client_name', 'Cacao Farmers Coop')->first();

        $header = StockOut::firstOrCreate(
            [
                'client_id' => $client->client_id,
                'release_date' => '2026-09-10',
            ],
            [
                'user_id' => $admin->id,
                'total_amount' => null,
            ],
        );

        if ($header->wasRecentlyCreated) {
            $releasedFromB002 = 20;

            if ($releasedFromB002 > (int) $b002->available_count) {
                $releasedFromB002 = (int) $b002->available_count;
            }

            $header->details()->create([
                'batch_id' => $b002->batch_id,
                'quantity' => $releasedFromB002,
                'unit' => 'seedlings',
                'unit_price' => 25.00,
                'amount' => $releasedFromB002 * 25.00,
            ]);

            $header->forceFill(['total_amount' => $releasedFromB002 * 25.00])->save();
            $b002->decrement('available_count', $releasedFromB002);
        }

        // Monthly inventory snapshots (September 2026) for the seeded batches.
        $b002->refresh();

        foreach ([$b001, $b002, $b003] as $batch) {
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
                'viable' => (int) $batch->viable_count,
                'regraft' => $batch->totalReplanted(),
                'mortality' => $batch->effectiveMortalityQuantity(),
                'last_update' => now(),
            ]);
        }

        // Scion Receiving: a receiving/cost-tracking log ONLY. It carries no
        // stock balance and has no freshness/viability column (deliberately not
        // implemented pending client confirmation).
        $receivings = [
            ['scion_variety_id' => $criollo->scion_variety_id, 'supplier_name' => 'Davao Cacao Growers', 'quantity' => 120, 'unit_price' => 12.50, 'date_received' => '2026-08-15', 'origin' => 'Davao del Sur', 'notes' => 'Premium Criollo scion bundle.'],
            ['scion_variety_id' => $trinitario->scion_variety_id, 'supplier_name' => null, 'quantity' => 80, 'unit_price' => null, 'date_received' => '2026-08-20', 'origin' => null, 'notes' => null],
        ];

        foreach ($receivings as $receiving) {
            $supplierId = $receiving['supplier_name'] === null
                ? null
                : Supplier::where('supplier_name', $receiving['supplier_name'])->value('supplier_id');

            ScionReceiving::firstOrCreate(
                [
                    'scion_variety_id' => $receiving['scion_variety_id'],
                    'date_received' => $receiving['date_received'],
                ],
                [
                    'supplier_id' => $supplierId,
                    'quantity' => $receiving['quantity'],
                    'unit_price' => $receiving['unit_price'],
                    'origin' => $receiving['origin'],
                    'notes' => $receiving['notes'],
                ],
            );
        }

        $this->call(ActivityLogSeeder::class);
    }
}
