<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\ReplantingRecord;
use App\Models\Scion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReplantingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
        ]);

        $batches = Batch::query()
            ->with(['variety', 'replantingRecords.user'])
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

        $totalDeficit = $summaries->sum('deficit');
        $totalReplanted = $summaries->sum('total_replanted');
        $totalMortality = $summaries->sum('effective_mortality');
        $batchesWithDeficit = $summaries->where('deficit', '>', 0)->count();

        $replantableBatches = $summaries
            ->where('deficit', '>', 0)
            ->reject(fn (array $summary) => $summary['batch']->isArchived())
            ->values();

        return view('operational.replanting.index', [
            'summaries' => $summaries,
            'replantableBatches' => $replantableBatches,
            'filters' => $filters,
            'totalDeficit' => $totalDeficit,
            'totalReplanted' => $totalReplanted,
            'totalMortality' => $totalMortality,
            'batchesWithDeficit' => $batchesWithDeficit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'replanting_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $batch = Batch::findOrFail($validated['batch_id']);
        $deficit = $batch->replantingDeficit();

        if ($validated['quantity'] > $deficit) {
            throw ValidationException::withMessages([
                'quantity' => "Replanting cannot exceed the batch's remaining deficit of {$deficit} for {$batch->batch_code}.",
            ]);
        }

        DB::transaction(function () use ($validated, $batch): void {
            // Lock the batch's scion pool and deduct the scions used (approved ERD
            // revision §9.10). RULE 7: the deduction is transaction-safe and the
            // available balance can never go negative.
            $scion = Scion::query()
                ->whereKey($batch->scion_id)
                ->lockForUpdate()
                ->first();

            $available = (int) ($scion?->quantity ?? 0);

            if (! $scion || $validated['quantity'] > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "Not enough available scions for {$batch->batch_code}: {$available} remaining, {$validated['quantity']} requested.",
                ]);
            }

            ReplantingRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => Auth::id(),
                'quantity' => $validated['quantity'],
                'replanting_date' => $validated['replanting_date'],
                'remark' => $validated['remark'] ?? null,
            ]);

            $scion->deduct((int) $validated['quantity']);

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_REPLANTING_RECORDED,
                "Recorded {$validated['quantity']} replanting for {$batch->batch_code}. Available scions: {$scion->quantity}.",
                null,
                $validated['replanting_date'],
            );
        });

        return redirect()
            ->route('operational.replanting')
            ->with('success', 'Replanting record saved successfully.');
    }
}
