<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\StockOut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $status = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = Client::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('client_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $status === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $clients = (clone $visible)
            ->withCount('stockOuts')
            ->orderBy('client_name')
            ->paginate(10)
            ->withQueryString();

        $totalClients = (clone $visible)->count();
        $clientsWithActivity = (clone $visible)->has('stockOuts')->count();
        $totalStockOuts = (int) StockOut::query()
            ->whereIn('client_id', (clone $visible)->select('client_id'))
            ->count();

        return view('inventory.clients.index', [
            'clients' => $clients,
            'filters' => $filters,
            'status' => $status,
            'archivedCount' => $archivedCount,
            'totalClients' => $totalClients,
            'clientsWithActivity' => $clientsWithActivity,
            'totalStockOuts' => $totalStockOuts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), Client::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.clients')
                ->withErrors($validator, 'client')
                ->withInput();
        }

        Client::create($validator->validated());

        return redirect()
            ->route('inventory.clients')
            ->with('success', 'Client added successfully.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $validator = Validator::make($request->all(), Client::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.clients')
                ->withErrors($validator, 'client')
                ->withInput(['edit_client_id' => $client->client_id]);
        }

        $client->update($validator->validated());

        return redirect()
            ->route('inventory.clients')
            ->with('success', 'Client updated successfully.');
    }

    /**
     * Archive a client instead of deleting it (approved ERD revision §9.8:
     * archive/restore replaces hard deletion so history is never destroyed).
     */
    public function archive(Client $client): RedirectResponse
    {
        if ($client->isArchived()) {
            return redirect()
                ->route('inventory.clients')
                ->with('error', 'This client is already archived.');
        }

        DB::transaction(function () use ($client): void {
            $client->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_CLIENT_ARCHIVED,
                "Archived client: {$client->client_name}."
            );
        });

        return redirect()
            ->route('inventory.clients')
            ->with('success', 'Client archived successfully.');
    }

    /**
     * Restore a previously archived client to the active directory.
     */
    public function restore(Client $client): RedirectResponse
    {
        if (! $client->isArchived()) {
            return redirect()
                ->route('inventory.clients')
                ->with('error', 'This client is not archived.');
        }

        DB::transaction(function () use ($client): void {
            $client->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_CLIENT_RESTORED,
                "Restored client: {$client->client_name}."
            );
        });

        return redirect()
            ->route('inventory.clients', ['tab' => 'archived'])
            ->with('success', 'Client restored successfully.');
    }
}
