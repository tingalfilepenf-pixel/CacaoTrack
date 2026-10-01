<?php

namespace App\Http\Controllers;

use App\Actions\CorrectRecord;
use App\Http\Requests\CorrectMortalityRecordRequest;
use App\Http\Requests\CorrectRegraftRecordRequest;
use App\Http\Requests\CorrectStockInRequest;
use App\Http\Requests\CorrectStockOutRequest;
use App\Models\ActivityLog;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\Role;
use App\Models\StockIn;
use App\Models\StockOut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Correction of mistaken operational entries (OD-5) — mortality, regraft,
 * stock-out and stock-in records.
 *
 * No new table and no revival of the removed Mortality_Adjustments /
 * Stock_Reversal concepts: a correction re-validates against the same business
 * rules as a new entry, applies the live-balance changes atomically and writes
 * the before/after values to the existing Activity_Log through the one shared
 * writer ({@see CorrectRecord}, {@see ActivityLog::recordCorrection()}).
 *
 * Every route is gated by the `correct_records` access restriction, which is
 * administrator-only by default (see {@see Role}).
 */
class CorrectionController extends Controller
{
    public function __construct(private readonly CorrectRecord $corrections) {}

    public function mortality(CorrectMortalityRecordRequest $request, MortalityRecord $mortality): RedirectResponse
    {
        $this->corrections->mortality($mortality, $request->validated(), Auth::id());

        return redirect()
            ->route('operational.mortality')
            ->with('success', 'Mortality record corrected successfully.');
    }

    public function regraft(CorrectRegraftRecordRequest $request, RegraftRecord $regraft): RedirectResponse
    {
        $this->corrections->regraft($regraft, $request->validated(), Auth::id());

        return redirect()
            ->route('operational.regraft')
            ->with('success', 'Regraft record corrected successfully.');
    }

    public function stockIn(CorrectStockInRequest $request, StockIn $stockIn): RedirectResponse
    {
        $this->corrections->stockIn($stockIn, $request->validated(), Auth::id());

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Stock-in record corrected successfully.');
    }

    public function stockOut(CorrectStockOutRequest $request, StockOut $stockOut): RedirectResponse
    {
        $this->corrections->stockOut($stockOut, $request->validated(), Auth::id());

        return redirect()
            ->route('inventory.stock-out')
            ->with('success', 'Stock-out record corrected successfully.');
    }
}
