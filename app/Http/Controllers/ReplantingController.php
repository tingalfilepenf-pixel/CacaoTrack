<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReplantingRecordRequest;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\ReplantingRecord;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReplantingController extends Controller
{
    /**
     * FR-05 Replanting listing. Replanting is linked to the batch and its
     * deficit (effective mortality − total replanted); the removed scion stock
     * pool is no longer involved.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
        ]);

        $batches = Batch::query()
            ->with(['scionVariety', 'replantingRecords.user'])
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->orderBy('batch_code')
            ->get();

        $summaries = $batches->map(function (Batch $batch): array {
            $effectiveMortality = $batch->effectiveMortalityQuantity();
            $totalReplanted = $batch->totalReplanted();

            return [
                'batch' => $batch,
                'effective_mortality' => $effectiveMortality,
                'total_replanted' => $totalReplanted,
                'deficit' => max(0, $effectiveMortality - $totalReplanted),
                'history' => $batch->replantingRecords
                    ->sortByDesc('replanting_date')
                    ->values(),
            ];
        });

        $replantableBatches = $summaries
            ->where('deficit', '>', 0)
            ->reject(fn (array $summary) => $summary['batch']->isArchived())
            ->values();

        return view('operational.replanting.index', [
            'summaries' => $summaries,
            'replantableBatches' => $replantableBatches,
            'filters' => $filters,
            'totalDeficit' => $summaries->sum('deficit'),
            'totalReplanted' => $summaries->sum('total_replanted'),
            'totalMortality' => $summaries->sum('effective_mortality'),
            'batchesWithDeficit' => $summaries->where('deficit', '>', 0)->count(),
        ]);
    }

    /**
     * Record a replanting entry against the batch's remaining deficit. The
     * removed Scion stock-pool lock/deduction is gone (revised ERD §6).
     */
    public function store(StoreReplantingRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fingerprint = DuplicateSubmissionGuard::assertFresh('replanting', $validated, 'replanting');

        try {
            DB::transaction(function () use ($validated): void {
                $batch = Batch::query()->lockForUpdate()->findOrFail($validated['batch_id']);

                $deficit = $batch->replantingDeficit();

                if ((int) $validated['quantity'] > $deficit) {
                    $this->failValidation([
                        'quantity' => "Regraft cannot exceed the batch's remaining deficit of {$deficit} for {$batch->batch_code}.",
                    ], 'replanting');
                }

                ReplantingRecord::create([
                    'batch_id' => $batch->batch_id,
                    'user_id' => Auth::id(),
                    'quantity' => $validated['quantity'],
                    'replanting_date' => $validated['replanting_date'],
                    'remark' => $validated['remark'] ?? null,
                ]);

                ActivityLog::record(
                    $batch->batch_id,
                    ActivityLog::TYPE_REPLANTING_RECORDED,
                    "Recorded {$validated['quantity']} regraft for {$batch->batch_code}.",
                    null,
                    $validated['replanting_date'],
                );
            });
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('replanting', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.replanting')
            ->with('success', 'Regraft record saved successfully.');
    }
}
