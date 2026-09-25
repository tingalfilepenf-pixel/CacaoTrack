<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Scion;
use App\Support\ScionMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ScionController extends Controller
{
    /**
     * Scion Management (FR-14). Scions are fungible stock used only for
     * replanting/re-grafting, so the page shows the single scion pool and its
     * available balance (approved ERD revision §2.8/§9.10).
     */
    public function index(Request $request): View
    {
        $scion = Scion::query()->orderBy('scion_id')->first();

        return view('inventory.scions.index', [
            'scion' => $scion,
            'metrics' => ScionMetrics::forScions($scion ? [$scion->scion_id] : []),
            'batchCount' => $scion ? $scion->batches()->count() : 0,
        ]);
    }

    /**
     * Receive scion stock: credits the single scion pool inside a DB
     * transaction and writes an audit row (approved ERD revision §9.10;
     * RULE 6/7).
     */
    public function receive(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.scions')
                ->withErrors($validator, 'scion')
                ->withInput();
        }

        $quantity = (int) $validator->validated()['quantity'];

        DB::transaction(function () use ($quantity): void {
            $scion = Scion::query()->orderBy('scion_id')->lockForUpdate()->first()
                ?? Scion::create(['scion_name' => 'Scion', 'quantity' => 0]);

            $scion->receive($quantity);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SCION_STOCK_RECEIVED,
                "Received {$quantity} scions. Available scion balance: {$scion->quantity}."
            );
        });

        return redirect()
            ->route('inventory.scions')
            ->with('success', 'Scion stock received successfully.');
    }
}
