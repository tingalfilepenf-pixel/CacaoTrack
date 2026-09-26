<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScionReceivingRequest;
use App\Models\ScionReceiving;
use App\Models\ScionVariety;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Scion Receiving log (ERD §3.16).
 *
 * The approved additive `Scion_Receiving` entity records the receipt of scion
 * material for a Scion Variety. It is a log only: it holds no stock balance on
 * `scion_varieties` and does not touch Regraft/Replanting. This controller
 * currently exposes the listing and the "Add Scion Receiving" store action;
 * Edit/Archive/Details and any grafting/regrafting traceability linkage are
 * deliberately out of scope for this pass.
 */
class ScionReceivingController extends Controller
{
    public function index(): View
    {
        $receivings = ScionReceiving::query()
            ->with(['scionVariety', 'supplier'])
            ->orderByDesc('date_received')
            ->orderByDesc('scion_receiving_id')
            ->paginate(10)
            ->withQueryString();

        return view('operational.scion-receivings.index', [
            'receivings' => $receivings,
            'scionVarieties' => ScionVariety::query()->active()->orderBy('scion_variety_name')->get(),
            'suppliers' => Supplier::query()->active()->orderBy('supplier_name')->get(),
        ]);
    }

    public function store(StoreScionReceivingRequest $request): RedirectResponse
    {
        ScionReceiving::create($request->validated());

        return redirect()
            ->route('operational.scion-receivings')
            ->with('success', 'Scion receiving recorded successfully.');
    }
}
