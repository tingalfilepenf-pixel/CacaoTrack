<?php

namespace App\Http\Controllers;

use App\Actions\RecordMortality;
use App\Http\Requests\StoreMortalityRecordRequest;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MortalityController extends Controller
{
    public function __construct(private readonly RecordMortality $records) {}

    /**
     * FR-04 Mortality Tracking listing. Mortality has no adjustment/correction
     * action any more (Mortality_Adjustments was removed with the revised
     * model).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = MortalityRecord::query()
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->when($filters['scion_variety_id'] ?? null, fn ($query, $scionVarietyId) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('scion_variety_id', $scionVarietyId),
            ))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '<=', $date));

        $filteredRecords = (clone $base)->get();

        $records = (clone $base)
            ->with(['batch.scionVariety', 'user'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('mortality_id')
            ->paginate(10)
            ->withQueryString();

        $activeBatches = Batch::active()->with('scionVariety')->orderBy('batch_code')->get();

        // Each batch's original / Stock Out / mortality / remaining figures,
        // kept separate so the dropdown never shows a live balance as the
        // historical original quantity (RULE 6: one shared definition).
        $activeBatchBreakdowns = $activeBatches->mapWithKeys(
            fn (Batch $batch) => [$batch->batch_id => $batch->quantityBreakdown()],
        );

        return view('operational.mortality.index', [
            'records' => $records,
            'batches' => Batch::with('scionVariety')->orderBy('batch_code')->get(),
            'activeBatches' => $activeBatches,
            'activeBatchBreakdowns' => $activeBatchBreakdowns,
            'scionVarieties' => ScionVariety::orderBy('scion_variety_name')->get(),
            'filters' => $filters,
            'totalMortality' => $filteredRecords->sum(fn (MortalityRecord $record) => $record->effectiveQuantity()),
            'recordCount' => $filteredRecords->count(),
            'batchesAffected' => $filteredRecords->pluck('batch_id')->unique()->count(),
        ]);
    }

    /**
     * Record mortality. This action only ever creates a Mortality_Record row —
     * it never writes `batches.available_count` (mortality does not change the
     * live available balance or the regraft counter). The remaining capacity
     * comes from the model's own cap logic.
     */
    public function store(StoreMortalityRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fingerprint = DuplicateSubmissionGuard::assertFresh('mortality', $validated, 'mortality');

        try {
            $this->records->execute($validated, (int) Auth::id());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('mortality', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.mortality')
            ->with('success', 'Mortality record saved successfully.');
    }
}
