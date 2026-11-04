<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalSubmissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_submissions_table_has_expected_columns_and_types(): void
    {
        $this->assertTrue(Schema::hasTable('operational_submissions'));

        $columns = collect(Schema::getColumns('operational_submissions'))->keyBy('name');

        $this->assertEqualsCanonicalizing([
            'submission_id',
            'submission_type',
            'status',
            'payload',
            'submitted_by_id',
            'submitted_at',
            'decided_by_id',
            'decided_at',
            'decision_reason',
            'decision_automatic',
            'resubmission_of_id',
            'subject_batch_id',
            'created_at',
            'updated_at',
        ], $columns->keys()->all());

        $this->assertSame('bigint', $columns['submission_id']['type_name']);
        $this->assertTrue($columns['submission_id']['auto_increment']);
        $this->assertSame('varchar', $columns['submission_type']['type_name']);
        $this->assertSame('varchar', $columns['status']['type_name']);
        $this->assertSame('pending', $columns['status']['default']);
        $this->assertSame('json', $columns['payload']['type_name']);
        $this->assertSame('datetime', $columns['submitted_at']['type_name']);
        $this->assertSame('datetime', $columns['decided_at']['type_name']);
        $this->assertSame('text', $columns['decision_reason']['type_name']);
        $this->assertFalse($columns['submitted_at']['nullable']);
        $this->assertTrue($columns['decided_by_id']['nullable']);
        $this->assertTrue($columns['decided_at']['nullable']);
        $this->assertTrue($columns['decision_reason']['nullable']);
        $this->assertTrue($columns['resubmission_of_id']['nullable']);
        $this->assertTrue($columns['subject_batch_id']['nullable']);
        $this->assertSame('0', $columns['decision_automatic']['default']);
    }

    public function test_operational_submission_records_table_has_expected_columns_and_types(): void
    {
        $this->assertTrue(Schema::hasTable('operational_submission_records'));

        $columns = collect(Schema::getColumns('operational_submission_records'))->keyBy('name');

        $this->assertEqualsCanonicalizing([
            'submission_record_id',
            'submission_id',
            'record_type',
            'record_id',
            'created_at',
        ], $columns->keys()->all());

        $this->assertSame('bigint', $columns['submission_record_id']['type_name']);
        $this->assertTrue($columns['submission_record_id']['auto_increment']);
        $this->assertSame('bigint', $columns['submission_id']['type_name']);
        $this->assertSame('varchar', $columns['record_type']['type_name']);
        $this->assertSame('bigint', $columns['record_id']['type_name']);
        $this->assertSame('timestamp', $columns['created_at']['type_name']);
        $this->assertFalse($columns['created_at']['nullable']);
        $this->assertSame('CURRENT_TIMESTAMP', $columns['created_at']['default']);
    }

    public function test_operational_submissions_has_expected_indexes_and_foreign_keys(): void
    {
        $indexes = collect(Schema::getIndexes('operational_submissions'));

        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['submission_id'] && $index['primary']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['status'] && ! $index['unique']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['submission_type', 'status'] && ! $index['unique']));

        $foreignKeys = collect(Schema::getForeignKeys('operational_submissions'))->keyBy(
            fn (array $foreignKey): string => $foreignKey['columns'][0]
        );

        $this->assertSame('users', $foreignKeys['submitted_by_id']['foreign_table']);
        $this->assertSame(['id'], $foreignKeys['submitted_by_id']['foreign_columns']);
        $this->assertSame('restrict', $foreignKeys['submitted_by_id']['on_delete']);

        $this->assertSame('users', $foreignKeys['decided_by_id']['foreign_table']);
        $this->assertSame('restrict', $foreignKeys['decided_by_id']['on_delete']);

        $this->assertSame('operational_submissions', $foreignKeys['resubmission_of_id']['foreign_table']);
        $this->assertSame(['submission_id'], $foreignKeys['resubmission_of_id']['foreign_columns']);
        $this->assertSame('restrict', $foreignKeys['resubmission_of_id']['on_delete']);

        $this->assertSame('batches', $foreignKeys['subject_batch_id']['foreign_table']);
        $this->assertSame(['batch_id'], $foreignKeys['subject_batch_id']['foreign_columns']);
        $this->assertSame('restrict', $foreignKeys['subject_batch_id']['on_delete']);
    }

    public function test_operational_submission_records_has_expected_indexes_and_foreign_key(): void
    {
        $indexes = collect(Schema::getIndexes('operational_submission_records'));

        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['submission_record_id'] && $index['primary']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['submission_id', 'record_type', 'record_id'] && $index['unique']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['record_type', 'record_id'] && $index['unique']));

        $foreignKeys = collect(Schema::getForeignKeys('operational_submission_records'))->keyBy(
            fn (array $foreignKey): string => $foreignKey['columns'][0]
        );

        $this->assertSame('operational_submissions', $foreignKeys['submission_id']['foreign_table']);
        $this->assertSame(['submission_id'], $foreignKeys['submission_id']['foreign_columns']);
        $this->assertSame('restrict', $foreignKeys['submission_id']['on_delete']);
    }

    public function test_factories_create_pending_submissions_and_link_rows(): void
    {
        $submitter = User::factory()->create();
        $batch = Batch::factory()->create();

        $submission = OperationalSubmission::factory()
            ->forBatch($batch)
            ->create(['submitted_by_id' => $submitter->id]);

        $this->assertTrue($submission->isPending());
        $this->assertSame(OperationalSubmission::TYPE_MORTALITY, $submission->submission_type);
        $this->assertIsArray($submission->payload);
        $this->assertTrue($submission->submitter->is($submitter));
        $this->assertTrue($submission->subjectBatch->is($batch));
        $this->assertNull($submission->decided_by_id);
        $this->assertNull($submission->decided_at);
        $this->assertFalse($submission->decision_automatic);

        $stockOut = OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_OUT)
            ->create(['submitted_by_id' => $submitter->id]);

        $this->assertSame(OperationalSubmission::TYPE_STOCK_OUT, $stockOut->submission_type);
        $this->assertArrayHasKey('lines', $stockOut->payload);

        $record = OperationalSubmissionRecord::factory()
            ->create(['submission_id' => $submission->submission_id]);

        $this->assertSame($submission->submission_id, $record->submission_id);
        $this->assertTrue($record->submission->is($submission));
        $this->assertContains($record->record_type, OperationalSubmissionRecord::RECORD_TYPES);
    }

    public function test_link_rows_reject_unknown_record_types_and_non_positive_ids(): void
    {
        $submission = OperationalSubmission::factory()->create();

        try {
            OperationalSubmissionRecord::factory()->create([
                'submission_id' => $submission->submission_id,
                'record_type' => 'unknown_record',
            ]);
            $this->fail('Expected an unknown record type to be rejected.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(DomainException::class);

        OperationalSubmissionRecord::factory()->create([
            'submission_id' => $submission->submission_id,
            'record_id' => 0,
        ]);
    }
}
