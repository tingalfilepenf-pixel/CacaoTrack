<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationalSubmissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_pending_submission_changes_no_official_rows(): void
    {
        $submitter = User::factory()->create();
        $batch = Batch::factory()->create();

        $officialTables = [
            'mortality_records',
            'stock_outs',
            'stock_out_details',
            'batches',
            'inventories',
        ];

        $before = [];
        foreach ($officialTables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        OperationalSubmission::factory()
            ->forBatch($batch)
            ->create(['submitted_by_id' => $submitter->id]);

        foreach ($officialTables as $table) {
            $this->assertSame(
                $before[$table],
                DB::table($table)->count(),
                "[{$table}] must not be written by a pending submission."
            );
        }
    }

    public function test_one_official_record_cannot_be_linked_to_two_submissions(): void
    {
        $first = OperationalSubmission::factory()->create();
        $second = OperationalSubmission::factory()->create();

        OperationalSubmissionRecord::factory()->create([
            'submission_id' => $first->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL,
            'record_id' => 50,
        ]);

        $this->expectException(QueryException::class);

        OperationalSubmissionRecord::factory()->create([
            'submission_id' => $second->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL,
            'record_id' => 50,
        ]);
    }

    public function test_one_submission_can_link_several_official_records(): void
    {
        $submission = OperationalSubmission::factory()->create();

        $rows = [
            [OperationalSubmissionRecord::RECORD_TYPE_MORTALITY, 11],
            [OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT, 11],
            [OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL, 11],
            [OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL, 12],
        ];

        foreach ($rows as [$recordType, $recordId]) {
            OperationalSubmissionRecord::factory()->create([
                'submission_id' => $submission->submission_id,
                'record_type' => $recordType,
                'record_id' => $recordId,
            ]);
        }

        $this->assertSame(4, $submission->records()->count());
    }

    public function test_the_same_record_cannot_be_linked_twice_to_one_submission(): void
    {
        $submission = OperationalSubmission::factory()->create();

        OperationalSubmissionRecord::factory()->create([
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
            'record_id' => 21,
        ]);

        $this->expectException(QueryException::class);

        OperationalSubmissionRecord::factory()->create([
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
            'record_id' => 21,
        ]);
    }

    public function test_pilot_catalogs_are_centralized(): void
    {
        $this->assertSame(
            [
                OperationalSubmission::TYPE_MORTALITY,
                OperationalSubmission::TYPE_STOCK_OUT,
                OperationalSubmission::TYPE_STOCK_IN,
            ],
            OperationalSubmission::TYPES
        );

        $this->assertSame(
            [
                OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
                OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT,
                OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL,
                OperationalSubmissionRecord::RECORD_TYPE_STOCK_IN,
            ],
            OperationalSubmissionRecord::RECORD_TYPES
        );

        $this->assertSame(
            [OperationalSubmission::STATUS_PENDING, OperationalSubmission::STATUS_APPROVED, OperationalSubmission::STATUS_REJECTED],
            OperationalSubmission::STATUSES
        );
    }

    public function test_seeders_console_and_providers_do_not_write_submission_tables(): void
    {
        $files = collect();

        foreach ([
            app_path('Console'),
            app_path('Providers'),
            base_path('database/seeders'),
        ] as $path) {
            if (File::isDirectory($path)) {
                $files = $files->merge(File::allFiles($path));
            }
        }

        $needles = [
            'operational_submissions',
            'operational_submission_records',
            'OperationalSubmission',
        ];

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $contents, $file->getPathname());
            }
        }
    }
}
