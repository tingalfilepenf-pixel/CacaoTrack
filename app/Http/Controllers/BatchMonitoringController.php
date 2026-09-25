<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\BatchMonitoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Batch Monitoring (FR-18, approved ERD revision §9.11).
 *
 * Read-only viability view: for each batch it shows the current **alive plant
 * count** (the stored `Batch.viable_count`, which Mortality Tracking decrements
 * as plants die — mortality is the only operation that updates plant status)
 * and a derived `Pending` / `Complete` status from the batch's real
 * `monitoring_start_date`. It captures no manual observations and never
 * fabricates a value (RULE 5/12); every lifecycle calculation is defined once in
 * `App\Models\BatchMonitoring` (RULE 6).
 */
class BatchMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'status' => ['nullable', 'string', Rule::in(array_keys(BatchMonitoring::STATUSES))],
            'search' => ['nullable', 'string', 'max:255'],
            'as_of' => ['nullable', 'date'],
        ]);

        $reference = isset($filters['as_of'])
            ? Carbon::parse($filters['as_of'])
            : Carbon::today();

        $batches = Batch::active()
            ->with('variety')
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('batch_code', 'like', "%{$search}%"))
            ->orderBy('batch_code')
            ->get();

        $rows = $batches->map(fn (Batch $batch): array => [
            'batch' => $batch,
            'status' => BatchMonitoring::statusFor($batch, $reference),
            'day' => $batch->monitoringDay($reference),
            'remaining' => $batch->monitoringRemainingDays($reference),
            'alive' => $batch->alivePlants(),
        ]);

        $statusFilter = $filters['status'] ?? null;
        $filteredRows = $statusFilter
            ? $rows->where('status', $statusFilter)->values()
            : $rows;

        return view('operational.batch-monitoring.index', [
            'rows' => $filteredRows,
            'allRows' => $rows,
            'filters' => $filters,
            'reference' => $reference,
            'recordableBatches' => Batch::active()->with('variety')->orderBy('batch_code')->get(),
            'totalBatches' => $rows->count(),
            'pendingCount' => $rows->where('status', 'pending')->count(),
            'completeCount' => $rows->where('status', 'complete')->count(),
            'totalAlive' => $rows->sum('alive'),
        ]);
    }

    public function start(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'monitoring_start_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($batch->monitoring_start_date) {
            return redirect()
                ->route('operational.batch-monitoring')
                ->with('error', "Monitoring for {$batch->batch_code} has already started.");
        }

        DB::transaction(function () use ($batch, $validated): void {
            $batch->monitoring_start_date = $validated['monitoring_start_date'];
            $batch->save();

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_BATCH_MONITORING_STARTED,
                "Started 21-day monitoring for {$batch->batch_code} on {$validated['monitoring_start_date']}.",
                null,
                $validated['monitoring_start_date'],
            );
        });

        return redirect()
            ->route('operational.batch-monitoring')
            ->with('success', "Monitoring started for {$batch->batch_code}.");
    }
}
