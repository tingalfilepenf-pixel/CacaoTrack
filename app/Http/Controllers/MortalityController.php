<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityAdjustment;
use App\Models\MortalityRecord;
use App\Models\Variety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MortalityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'variety_id' => ['nullable', 'integer', 'exists:varieties,variety_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = MortalityRecord::query()
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->when($filters['variety_id'] ?? null, fn ($query, $varietyId) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('variety_id', $varietyId),
            ))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '<=', $date));

        $filteredRecords = (clone $base)->with('adjustments')->get();

        $totalMortality = $filteredRecords->sum(fn (MortalityRecord $record) => $record->effectiveQuantity());
        $recordCount = $filteredRecords->count();
        $batchesAffected = $filteredRecords->pluck('batch_id')->unique()->count();

        $records = (clone $base)
            ->with(['batch.variety', 'user', 'adjustments.approver'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('mortality_id')
            ->paginate(10)
            ->withQueryString();

        return view('operational.mortality.index', [
            'records' => $records,
            'batches' => Batch::with('variety')->orderBy('batch_code')->get(),
            'activeBatches' => Batch::active()->with('variety')->orderBy('batch_code')->get(),
            'varieties' => Variety::orderBy('variety_name')->get(),
            'filters' => $filters,
            'totalMortality' => $totalMortality,
            'recordCount' => $recordCount,
            'batchesAffected' => $batchesAffected,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'cause' => ['required', 'string', 'max:255'],
            'recorded_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated): void {
            $batch = Batch::query()->lockForUpdate()->findOrFail($validated['batch_id']);

            $alive = $batch->alivePlants();

            if ($validated['quantity'] > $alive) {
                throw ValidationException::withMessages([
                    'quantity' => "Mortality cannot exceed the batch's alive plant count of {$alive}.",
                ]);
            }

            MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => Auth::id(),
                'quantity' => $validated['quantity'],
                'cause' => $validated['cause'],
                'recorded_at' => $validated['recorded_at'],
            ]);

            // Mortality is the only operation that updates plant status: deduct
            // the dead plants from the batch's alive count (stored viable_count).
            $batch->applyAliveDelta(-(int) $validated['quantity']);

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_MORTALITY_RECORDED,
                "Recorded {$validated['quantity']} mortality for {$batch->batch_code} due to {$validated['cause']}.",
                null,
                $validated['recorded_at'],
            );
        });

        return redirect()
            ->route('operational.mortality')
            ->with('success', 'Mortality record saved successfully.');
    }

    public function adjust(Request $request, MortalityRecord $mortality): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment_quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($mortality, $validated): void {
            $batch = Batch::query()->lockForUpdate()->findOrFail($mortality->batch_id);
            $delta = (int) $validated['adjustment_quantity'];

            $newEffective = $mortality->effectiveQuantity() + $delta;

            if ($newEffective < 0) {
                throw ValidationException::withMessages([
                    'adjustment_quantity' => 'This adjustment would make the effective mortality negative.',
                ]);
            }

            // A positive adjustment records additional deaths, so it cannot
            // exceed the batch's current alive count. A negative adjustment
            // restores plants back to the alive count.
            if ($delta > 0 && $delta > $batch->alivePlants()) {
                throw ValidationException::withMessages([
                    'adjustment_quantity' => "This adjustment would exceed the batch's alive plant count of {$batch->alivePlants()}.",
                ]);
            }

            MortalityAdjustment::create([
                'mortality_id' => $mortality->mortality_id,
                'approved_by_id' => Auth::id(),
                'adjustment_quantity' => $delta,
                'reason' => $validated['reason'],
                'adjusted_at' => now(),
            ]);

            // Keep the alive count consistent with the audit mortality total.
            $batch->applyAliveDelta(-$delta);

            $formattedDelta = ($delta > 0 ? '+' : '').$delta;

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_MORTALITY_ADJUSTED,
                "Adjusted mortality record #{$mortality->mortality_id} by {$formattedDelta} ({$validated['reason']}).",
            );
        });

        return redirect()
            ->route('operational.mortality')
            ->with('success', 'Mortality adjustment saved successfully.');
    }
}
