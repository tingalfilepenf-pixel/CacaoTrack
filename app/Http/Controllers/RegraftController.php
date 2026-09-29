<?php

namespace App\Http\Controllers;

use App\Actions\RegraftBatch;
use App\Http\Requests\StoreRegraftRecordRequest;
use App\Models\Batch;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegraftController extends Controller
{
    /**
     * FR-05 Regraft listing (Phase 7B).
     *
     * Regrafting moves a quantity out of a source batch into a linked sub-batch,
     * so the per-batch table reads the cached `batches.regraft` counter and the
     * history reads the `parent_batch_id` link recorded by the regraft action.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
        ]);

        $batches = Batch::query()
            ->with(['scionVariety', 'parent'])
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->orderBy('batch_code')
            ->get();

        $summaries = $batches->map(function (Batch $batch): array {
            return [
                'batch' => $batch,
                'total_grafted' => (int) $batch->total_grafted,
                'regraft' => $batch->totalRegrafted(),
                'effective_mortality' => $batch->effectiveMortalityQuantity(),
                'deficit' => $batch->regraftDeficit(),
                'regraftable' => $batch->regraftableQuantity(),
            ];
        });

        $regraftableBatches = $summaries
            ->where('regraftable', '>', 0)
            ->reject(fn (array $summary) => $summary['batch']->isArchived())
            ->values();

        $history = RegraftRecord::query()
            ->with(['batch.scionVariety', 'parent', 'user'])
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('parent_batch_id', $batchId)
                    ->orWhere('batch_id', $batchId),
            ))
            ->orderByDesc('regraft_date')
            ->orderByDesc('regraft_id')
            ->paginate(10)
            ->withQueryString();

        return view('operational.regraft.index', [
            'summaries' => $summaries,
            'regraftableBatches' => $regraftableBatches,
            'history' => $history,
            'scionVarieties' => ScionVariety::active()->orderBy('scion_variety_name')->get(),
            'filters' => $filters,
            'totalDeficit' => $summaries->sum('deficit'),
            'totalRegrafted' => $summaries->sum('regraft'),
            'totalMortality' => $summaries->sum('effective_mortality'),
            'batchesWithDeficit' => $summaries->where('deficit', '>', 0)->count(),
        ]);
    }

    /**
     * Record a regraft: the single {@see RegraftBatch} action moves the quantity
     * out of the source batch, creates the linked sub-batch and its regraft
     * record inside one DB transaction. The duplicate-submission guard wraps the
     * action so a double-clicked form cannot create two sub-batches.
     */
    public function store(StoreRegraftRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fingerprint = DuplicateSubmissionGuard::assertFresh('regraft', $validated, 'regraft');

        try {
            $source = Batch::query()->findOrFail($validated['batch_id']);

            $subBatch = (new RegraftBatch)->execute(
                $source,
                (int) $validated['quantity'],
                (int) $validated['scion_variety_id'],
                $validated['regraft_date'],
                $validated['remark'] ?? null,
            );
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('regraft', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.regraft')
            ->with('success', "Regraft recorded; sub-batch {$subBatch->batch_code} created.");
    }
}
