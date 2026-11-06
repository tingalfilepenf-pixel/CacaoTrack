<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCodeTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $code, ?int $parentId = null, string $source = Batch::SOURCE_OWN): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo '.$code]);
        $supplier = $source === Batch::SOURCE_SUPPLIER
            ? Supplier::create([
                'supplier_name' => 'Supplier '.$code,
                'contact_number' => '0917-000-0000',
                'address' => 'Test Address',
            ])
            : null;

        return Batch::create([
            'source' => $source,
            'supplier_id' => $supplier?->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'parent_batch_id' => $parentId,
            'user_id' => User::factory()->create()->id,
            'batch_code' => $code,
            'total_grafted' => 50,
            'regraft' => 0,
            'available_count' => 50,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_b_and_s_sequences_are_independent(): void
    {
        $this->batch('B001');
        $this->batch('B003');
        $this->batch('S001', null, Batch::SOURCE_SUPPLIER);
        $this->batch('CUSTOM-1');

        $this->assertSame('B004', Batch::nextBatchCode('B'));
        $this->assertSame('S002', Batch::nextBatchCode('S'));
    }

    public function test_sub_batch_codes_are_ignored_by_both_root_sequences(): void
    {
        $parentB = $this->batch('B001');
        $parentS = $this->batch('S001', null, Batch::SOURCE_SUPPLIER);
        $this->batch('B001-R1', $parentB->batch_id);
        $this->batch('S001-R1', $parentS->batch_id, Batch::SOURCE_SUPPLIER);

        $this->assertSame('B002', Batch::nextBatchCode('B'));
        $this->assertSame('S002', Batch::nextBatchCode('S'));
    }

    public function test_a_sub_batch_of_a_supplier_batch_continues_the_supplier_code(): void
    {
        $parent = $this->batch('S001', null, Batch::SOURCE_SUPPLIER);

        $this->assertSame('S001-R1', $parent->nextSubBatchCode());

        $this->batch('S001-R1', $parent->batch_id, Batch::SOURCE_SUPPLIER);

        $this->assertSame('S001-R2', $parent->nextSubBatchCode());
    }
}
