<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationalSubmissionRawSqlGuardTest extends TestCase
{
    public function test_raw_sql_writes_to_submission_tables_are_confined_to_the_intended_files(): void
    {
        $allowed = [
            'Actions/ApproveOperationalSubmission.php',
            'Actions/RejectOperationalSubmission.php',
            'Actions/SubmitOperationalSubmission.php',
            'Models/OperationalSubmission.php',
            'Models/OperationalSubmissionRecord.php',
        ];

        $files = collect(File::allFiles(app_path()))
            ->map(fn ($file) => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
            ->reject(fn (string $path) => in_array($path, $allowed, true))
            ->sort()
            ->values();

        $this->assertGreaterThan(0, $files->count(), 'Expected application files to scan.');

        $pattern = '/DB::table\(\s*[\'"]operational_submissions[\'"]\s*\)'
            .'|DB::table\(\s*[\'"]operational_submission_records[\'"]\s*\)'
            .'|\b(?:insert\s+into|update|delete\s+from)\s+[`"\']?(?:operational_submissions|operational_submission_records)\b/i';

        foreach ($files as $path) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                File::get(app_path($path)),
                "Raw SQL write to a submission table found in app/{$path}.",
            );
        }
    }
}
