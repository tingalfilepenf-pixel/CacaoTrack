<?php

namespace Tests\Feature;

use App\Support\OperationalCutover;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

class OperationalCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_cutover_is_an_explicit_asia_manila_instant(): void
    {
        $this->assertSame('2026-12-01 00:00:00', config('cacaotrack.operational_cutover.at'));
        $this->assertSame('Asia/Manila', config('cacaotrack.operational_cutover.timezone'));
        $this->assertSame('UTC', config('app.timezone'));

        $moment = OperationalCutover::moment();

        $this->assertSame('2026-12-01 00:00:00', $moment->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $moment->timezoneName);
        $this->assertSame('2026-11-30T16:00:00+00:00', $moment->utc()->toIso8601String());
    }

    public function test_has_occurred_compares_absolute_time_at_the_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-30 15:59:59', 'UTC'));
        $this->assertFalse(OperationalCutover::hasOccurred());

        Carbon::setTestNow(Carbon::parse('2026-11-30 16:00:00', 'UTC'));
        $this->assertTrue(OperationalCutover::hasOccurred());

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:01', 'Asia/Manila'));
        $this->assertTrue(OperationalCutover::hasOccurred());
    }

    public function test_no_route_controller_or_model_references_the_cutover_helper(): void
    {
        $files = collect(File::allFiles(app_path('Http/Controllers')))
            ->merge(File::allFiles(app_path('Models')))
            ->push(new SplFileInfo(base_path('routes/web.php')));

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString('OperationalCutover', $contents, $file->getPathname());
            $this->assertStringNotContainsString('operational_cutover', $contents, $file->getPathname());
        }
    }
}
