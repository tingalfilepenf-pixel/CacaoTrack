<?php

namespace Database\Seeders;

use App\Models\ActivityTask;
use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\MortalityAdjustment;
use App\Models\MortalityRecord;
use App\Models\PotType;
use App\Models\ReplantingRecord;
use App\Models\Role;
use App\Models\Scion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Development-only reference data. These records must not be confused
     * with real production data.
     */
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

        $varieties = [
            ['variety_name' => 'Criollo', 'description' => 'Fine-flavor cacao variety, relatively disease-sensitive.'],
            ['variety_name' => 'Trinitario', 'description' => 'Hybrid of Criollo and Forastero, good disease resistance.'],
            ['variety_name' => 'Forastero', 'description' => 'Hardy high-yield variety, widely cultivated.'],
        ];

        foreach ($varieties as $v) {
            Variety::updateOrCreate(['variety_name' => $v['variety_name']], $v);
        }

        // Approved ERD revision §2.8/§9.10: scions are a single generic stock pool
        // (not named varieties), so only one `Scion` row is seeded.
        $scion = Scion::updateOrCreate(
            ['scion_name' => 'Scion'],
            ['description' => null],
        );

        $potTypes = [
            ['pot_name' => 'Standard Polybag', 'description' => 'Standard black polyethylene bag, 40 cm x 20 cm.'],
            ['pot_name' => 'Root Trainer', 'description' => 'Deep cell tray for improved root development.'],
            ['pot_name' => 'Ceramic Pot', 'description' => 'Reusable ceramic pot for nursery display.'],
        ];

        foreach ($potTypes as $p) {
            PotType::updateOrCreate(['pot_name' => $p['pot_name']], $p);
        }

        $suppliers = [
            ['supplier_name' => 'Davao Cacao Growers', 'contact_number' => '0917-111-2233', 'address' => 'Calinan, Davao City'],
            ['supplier_name' => 'Bukidnon Seedling Supply', 'contact_number' => '0918-444-5566', 'address' => 'Malaybalay, Bukidnon'],
            ['supplier_name' => 'North Mindanao Nursery', 'contact_number' => '0920-777-8899', 'address' => 'Cagayan de Oro, Misamis Oriental'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['supplier_name' => $supplier['supplier_name']], $supplier);
        }

        $clients = [
            ['client_name' => 'Cacao Farmers Coop', 'contact_number' => '0922-111-2222', 'address' => 'Compostela Valley, Davao de Oro'],
            ['client_name' => 'Mountain View Estate', 'contact_number' => '0923-333-4444', 'address' => 'Marilog, Davao City'],
            ['client_name' => 'Green Pod Traders', 'contact_number' => '0925-555-6666', 'address' => 'Valencia City, Bukidnon'],
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(['client_name' => $client['client_name']], $client);
        }

        $batches = [
            [
                'variety_id' => Variety::where('variety_name', 'Criollo')->first()->variety_id,
                'scion_id' => $scion->scion_id,
                'pot_id' => PotType::where('pot_name', 'Standard Polybag')->first()->pot_id,
                'user_id' => $admin->id,
                'batch_code' => 'B001',
                'total_grafted' => 200,
                'viable_count' => 180,
                'available_count' => 180,
                'grafted_date' => '2026-07-15',
                'created_at' => now(),
                'status' => 'active',
            ],
            [
                'variety_id' => Variety::where('variety_name', 'Trinitario')->first()->variety_id,
                'scion_id' => $scion->scion_id,
                'pot_id' => PotType::where('pot_name', 'Root Trainer')->first()->pot_id,
                'user_id' => $admin->id,
                'batch_code' => 'B002',
                'total_grafted' => 150,
                'viable_count' => 140,
                'available_count' => 140,
                'grafted_date' => '2026-08-01',
                'created_at' => now(),
                'status' => 'active',
            ],
            [
                'variety_id' => Variety::where('variety_name', 'Forastero')->first()->variety_id,
                'scion_id' => $scion->scion_id,
                'pot_id' => PotType::where('pot_name', 'Ceramic Pot')->first()->pot_id,
                'user_id' => $admin->id,
                'batch_code' => 'B003',
                'total_grafted' => 100,
                'viable_count' => 95,
                'available_count' => 95,
                'grafted_date' => '2026-08-20',
                'created_at' => now(),
                'status' => 'active',
            ],
        ];

        foreach ($batches as $b) {
            Batch::updateOrCreate(['batch_code' => $b['batch_code']], $b);
        }

        $b001 = Batch::where('batch_code', 'B001')->first();
        $b002 = Batch::where('batch_code', 'B002')->first();
        $b003 = Batch::where('batch_code', 'B003')->first();

        foreach ([$b001, $b002, $b003] as $batch) {
            Inventory::syncFromBatch($batch, true);
        }

        $mortalityRecords = [
            ['batch_id' => $b001->batch_id, 'cause' => 'Disease', 'quantity' => 25, 'recorded_at' => '2026-09-01'],
            ['batch_id' => $b002->batch_id, 'cause' => 'Drought', 'quantity' => 15, 'recorded_at' => '2026-09-01'],
            ['batch_id' => $b003->batch_id, 'cause' => 'Pest', 'quantity' => 10, 'recorded_at' => '2026-08-28'],
        ];

        foreach ($mortalityRecords as $record) {
            MortalityRecord::updateOrCreate(
                [
                    'batch_id' => $record['batch_id'],
                    'cause' => $record['cause'],
                    'recorded_at' => $record['recorded_at'],
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => $record['quantity'],
                ],
            );
        }

        $firstRecord = MortalityRecord::where('batch_id', $b001->batch_id)
            ->where('cause', 'Disease')
            ->first();

        if ($firstRecord) {
            MortalityAdjustment::updateOrCreate(
                [
                    'mortality_id' => $firstRecord->mortality_id,
                    'adjustment_quantity' => -5,
                ],
                [
                    'approved_by_id' => $admin->id,
                    'reason' => 'Recount corrected an overstated mortality entry.',
                    'adjusted_at' => now(),
                ],
            );
        }

        $replantingRecords = [
            ['batch_id' => $b001->batch_id, 'quantity' => 15, 'replanting_date' => '2026-09-03', 'remark' => 'Replaced disease-affected seedlings.'],
            ['batch_id' => $b002->batch_id, 'quantity' => 15, 'replanting_date' => '2026-09-03', 'remark' => 'Full replanting of drought losses.'],
            ['batch_id' => $b003->batch_id, 'quantity' => 4, 'replanting_date' => '2026-09-04', 'remark' => 'Partial replanting; remaining stock pending.'],
        ];

        foreach ($replantingRecords as $record) {
            ReplantingRecord::updateOrCreate(
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

        $this->call(ActivityLogSeeder::class);
    }
}
