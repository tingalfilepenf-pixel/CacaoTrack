<?php

namespace App\Http\Controllers;

use App\Actions\ApproveOperationalSubmission;
use App\Actions\RejectOperationalSubmission;
use App\Actions\SubmitOperationalSubmission;
use App\Exceptions\ApprovalRevalidationException;
use App\Http\Requests\RejectOperationalSubmissionRequest;
use App\Http\Requests\StoreBatchCreationSubmissionRequest;
use App\Http\Requests\StoreMortalitySubmissionRequest;
use App\Http\Requests\StoreStockInSubmissionRequest;
use App\Http\Requests\StoreStockOutSubmissionRequest;
use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\ScionVariety;
use App\Models\StockOutDetail;
use App\Models\Supplier;
use App\Support\DuplicateSubmissionGuard;
use App\Support\OperationalSubmissionVisibility;
use App\Support\StockBalances;
use App\Support\SubmissionWorkflowSwitch;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 3 Mortality / Stock Out submission capture and review surface.
 *
 * Every route is behind `submissions.enabled`; the direct official writes stay
 * unchanged while the switch is OFF.
 */
class OperationalSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmitOperationalSubmission $submissions,
        private readonly ApproveOperationalSubmission $approvals,
        private readonly RejectOperationalSubmission $rejections,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $search = trim((string) $request->query('q', ''));
        $typeFilter = (string) $request->query('type', '');
        $statusFilter = (string) $request->query('status', '');

        if (! in_array($typeFilter, OperationalSubmission::TYPES, true)) {
            $typeFilter = '';
        }

        if (! in_array($statusFilter, OperationalSubmission::STATUSES, true)) {
            $statusFilter = '';
        }

        $query = OperationalSubmissionVisibility::scope(
            OperationalSubmission::query()->with(['submitter', 'subjectBatch', 'decider', 'resubmissionOf']),
            $user,
        );

        $this->applySearch($query, $search);

        if ($typeFilter !== '') {
            $query->where('submission_type', $typeFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $submissions = $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->paginate(10)
            ->withQueryString();

        $display = $this->displayData($submissions->getCollection());

        return view('operational.submissions.index', array_merge([
            'submissions' => $submissions,
            'canSubmit' => $user->hasPermission('submit_operations'),
            'canVerify' => $user->hasPermission('verify_operations'),
            'canViewAll' => OperationalSubmissionVisibility::canViewAll($user),
            'stockInSubmissionEnabled' => SubmissionWorkflowSwitch::stockInEnabled(),
            'batchCreationEnabled' => SubmissionWorkflowSwitch::batchCreationEnabled(),
            'search' => $search,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'hasFilters' => $search !== '' || $typeFilter !== '' || $statusFilter !== '',
        ], $display));
    }

    public function queue(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $typeFilter = (string) $request->query('type', '');
        $statusFilter = (string) $request->query('status', OperationalSubmission::STATUS_PENDING);

        if (! in_array($typeFilter, OperationalSubmission::TYPES, true)) {
            $typeFilter = '';
        }

        if (! in_array($statusFilter, OperationalSubmission::STATUSES, true)) {
            $statusFilter = OperationalSubmission::STATUS_PENDING;
        }

        $baseQuery = OperationalSubmissionVisibility::scope(
            OperationalSubmission::query()->with(['submitter', 'subjectBatch', 'decider']),
            $request->user(),
        );

        $this->applySearch($baseQuery, $search);

        if ($typeFilter !== '') {
            $baseQuery->where('submission_type', $typeFilter);
        }

        $statusCounts = [];

        foreach (OperationalSubmission::STATUSES as $status) {
            $statusCounts[$status] = (clone $baseQuery)->where('status', $status)->count();
        }

        $query = (clone $baseQuery)->where('status', $statusFilter);

        if ($statusFilter === OperationalSubmission::STATUS_PENDING) {
            $query->orderBy('submitted_at')->orderBy('submission_id');
        } else {
            $query->orderByDesc('decided_at')->orderByDesc('submission_id');
        }

        $submissions = $query->paginate(10)->withQueryString();

        $display = $this->displayData($submissions->getCollection());

        return view('operational.submissions.queue', array_merge([
            'submissions' => $submissions,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'statusCounts' => $statusCounts,
            'hasFilters' => $search !== '' || $typeFilter !== '',
            'stockInSubmissionEnabled' => SubmissionWorkflowSwitch::stockInEnabled(),
            'batchCreationEnabled' => SubmissionWorkflowSwitch::batchCreationEnabled(),
        ], $display));
    }

    /**
     * Apply the shared subject/batch/client/supplier search to a submission query.
     *
     * @param  Builder<OperationalSubmission>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $matchingClientIds = Client::query()
            ->where('client_name', 'like', $like)
            ->pluck('client_id')
            ->all();
        $matchingBatchIds = Batch::query()
            ->where('batch_code', 'like', $like)
            ->pluck('batch_id')
            ->all();
        $matchingSupplierIds = Supplier::query()
            ->where('supplier_name', 'like', $like)
            ->pluck('supplier_id')
            ->all();
        $matchingVarietyIds = ScionVariety::query()
            ->where('scion_variety_name', 'like', $like)
            ->pluck('scion_variety_id')
            ->all();

        $query->where(function ($inner) use ($like, $matchingClientIds, $matchingBatchIds, $matchingSupplierIds, $matchingVarietyIds): void {
            $inner->where('submission_id', 'like', $like)
                ->orWhere('submission_type', 'like', $like)
                ->orWhereHas('submitter', fn ($submitter) => $submitter->where('name', 'like', $like))
                ->orWhereHas('subjectBatch', fn ($batch) => $batch->where('batch_code', 'like', $like));

            if ($matchingClientIds !== []) {
                $inner->orWhereIn('payload->client_id', $matchingClientIds);
            }

            if ($matchingSupplierIds !== []) {
                $inner->orWhereIn('payload->seedling->supplier_id', $matchingSupplierIds)
                    ->orWhereIn('payload->scion->supplier_id', $matchingSupplierIds)
                    ->orWhereIn('payload->supplier_id', $matchingSupplierIds);
            }

            if ($matchingVarietyIds !== []) {
                $inner->orWhereIn('payload->scion->scion_variety_id', $matchingVarietyIds)
                    ->orWhereIn('payload->scion_variety_id', $matchingVarietyIds);
            }

            foreach ($matchingBatchIds as $batchId) {
                $inner->orWhereJsonContains('payload->details', ['batch_id' => (int) $batchId]);
            }
        });
    }

    /**
     * Resolve the display names referenced by the submissions on the page.
     *
     * @param  Collection<int, OperationalSubmission>  $pageSubmissions
     * @return array{clientNames: array<int, string>, batchCodes: array<int, string>, supplierNames: array<int, string>, varietyNames: array<int, string>, officialBatchCodes: array<int, string|null>}
     */
    private function displayData(Collection $pageSubmissions): array
    {
        $clientIds = $pageSubmissions
            ->where('submission_type', OperationalSubmission::TYPE_STOCK_OUT)
            ->map(fn (OperationalSubmission $submission): int => (int) ($submission->payload['client_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        $batchIds = $pageSubmissions
            ->flatMap(function (OperationalSubmission $submission): array {
                if ($submission->submission_type === OperationalSubmission::TYPE_MORTALITY) {
                    return [(int) ($submission->subject_batch_id ?? ($submission->payload['batch_id'] ?? 0))];
                }

                if ($submission->submission_type === OperationalSubmission::TYPE_STOCK_OUT) {
                    return collect($submission->payload['details'] ?? [])
                        ->pluck('batch_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values();

        $supplierIds = $pageSubmissions
            ->flatMap(function (OperationalSubmission $submission): array {
                if ($submission->submission_type === OperationalSubmission::TYPE_STOCK_IN) {
                    return [
                        (int) ($submission->payload['seedling']['supplier_id'] ?? 0),
                        (int) ($submission->payload['scion']['supplier_id'] ?? 0),
                    ];
                }

                if ($submission->submission_type === OperationalSubmission::TYPE_BATCH_CREATION) {
                    return [(int) ($submission->payload['supplier_id'] ?? 0)];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values();

        $varietyIds = $pageSubmissions
            ->flatMap(function (OperationalSubmission $submission): array {
                if ($submission->submission_type === OperationalSubmission::TYPE_STOCK_IN) {
                    return [(int) ($submission->payload['scion']['scion_variety_id'] ?? 0)];
                }

                if ($submission->submission_type === OperationalSubmission::TYPE_BATCH_CREATION) {
                    return [(int) ($submission->payload['scion_variety_id'] ?? 0)];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values();

        $batchSubmissionIds = $pageSubmissions
            ->where('submission_type', OperationalSubmission::TYPE_BATCH_CREATION)
            ->pluck('submission_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $officialBatchCodes = [];

        if ($batchSubmissionIds->isNotEmpty()) {
            $links = OperationalSubmissionRecord::query()
                ->whereIn('submission_id', $batchSubmissionIds)
                ->where('record_type', OperationalSubmissionRecord::RECORD_TYPE_BATCH)
                ->get(['submission_id', 'record_id']);

            $codes = Batch::query()
                ->whereIn('batch_id', $links->pluck('record_id'))
                ->pluck('batch_code', 'batch_id');

            foreach ($links as $link) {
                $officialBatchCodes[(int) $link->submission_id] = $codes[(int) $link->record_id] ?? null;
            }
        }

        return [
            'clientNames' => Client::query()
                ->whereIn('client_id', $clientIds)
                ->pluck('client_name', 'client_id')
                ->all(),
            'batchCodes' => Batch::query()
                ->whereIn('batch_id', $batchIds)
                ->pluck('batch_code', 'batch_id')
                ->all(),
            'supplierNames' => Supplier::query()
                ->whereIn('supplier_id', $supplierIds)
                ->pluck('supplier_name', 'supplier_id')
                ->all(),
            'varietyNames' => ScionVariety::query()
                ->whereIn('scion_variety_id', $varietyIds)
                ->pluck('scion_variety_name', 'scion_variety_id')
                ->all(),
            'officialBatchCodes' => $officialBatchCodes,
        ];
    }

    public function show(Request $request, OperationalSubmission $submission): View
    {
        $user = $request->user();

        abort_unless(OperationalSubmissionVisibility::canView($user, $submission), 404);

        $submission->load(['submitter', 'decider', 'subjectBatch', 'resubmissionOf', 'records']);

        $supplierIds = collect([
            $submission->payload['seedling']['supplier_id'] ?? null,
            $submission->payload['scion']['supplier_id'] ?? null,
            $submission->payload['supplier_id'] ?? null,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $varietyIds = collect([
            $submission->payload['scion']['scion_variety_id'] ?? null,
            $submission->payload['scion_variety_id'] ?? null,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $batchBalances = null;

        if ($submission->submission_type === OperationalSubmission::TYPE_BATCH_CREATION
            && ($submission->payload['source'] ?? null) === Batch::SOURCE_OWN) {
            $variety = ScionVariety::find((int) ($submission->payload['scion_variety_id'] ?? 0));
            $batchBalances = [
                'seedlings' => StockBalances::seedlingsAvailable(),
                'scion' => $variety?->availableScionQuantity(),
            ];
        }

        $display = $this->displayData(collect([$submission]));

        return view('operational.submissions.show', array_merge([
            'submission' => $submission,
            'canVerify' => $user->hasPermission('verify_operations'),
            'canSubmit' => $user->hasPermission('submit_operations'),
            'canViewAll' => OperationalSubmissionVisibility::canViewAll($user),
            'stockInSubmissionEnabled' => SubmissionWorkflowSwitch::stockInEnabled(),
            'batchCreationEnabled' => SubmissionWorkflowSwitch::batchCreationEnabled(),
            'batchBalances' => $batchBalances,
            'stockInSuppliers' => Supplier::query()
                ->whereIn('supplier_id', $supplierIds)
                ->pluck('supplier_name', 'supplier_id'),
            'stockInVarieties' => ScionVariety::query()
                ->whereIn('scion_variety_id', $varietyIds)
                ->pluck('scion_variety_name', 'scion_variety_id'),
        ], $display));
    }

    public function approve(Request $request, OperationalSubmission $submission): RedirectResponse
    {
        $managerId = (int) $request->user()->id;

        try {
            $this->approvals->execute($submission, $managerId);
        } catch (ApprovalRevalidationException $exception) {
            try {
                $this->rejections->automatic($submission, $managerId, $exception->getMessage());
            } catch (\Throwable $rejectionFailure) {
                report($rejectionFailure);

                return redirect()
                    ->route('operational.submissions.show', $submission)
                    ->with('error', 'Approval failed revalidation, but the submission could not be rejected and is still Pending. Please try again.');
            }

            return redirect()
                ->route('operational.submissions.show', $submission)
                ->with('error', 'Approval failed revalidation. The submission was automatically rejected: '.$exception->getMessage());
        } catch (DomainException|ValidationException $exception) {
            return redirect()
                ->route('operational.submissions.show', $submission)
                ->with('error', $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : $exception->getMessage());
        }

        return redirect()
            ->route('operational.submissions.show', $submission)
            ->with('success', 'Submission approved; official record created.');
    }

    public function reject(RejectOperationalSubmissionRequest $request, OperationalSubmission $submission): RedirectResponse
    {
        try {
            $this->rejections->manual(
                $submission,
                (int) $request->user()->id,
                (string) $request->validated()['reason'],
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('operational.submissions.show', $submission)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('operational.submissions.show', $submission)
            ->with('success', 'Submission rejected.');
    }

    public function createMortality(): View
    {
        return view('operational.submissions.mortality-create', [
            'batches' => Batch::submissionEligible()
                ->with('scionVariety')
                ->orderBy('batch_code')
                ->get(),
            'causes' => MortalityRecord::CAUSES,
        ]);
    }

    public function createResubmission(Request $request, OperationalSubmission $submission): View
    {
        $this->assertResubmittable($request, $submission);

        if ($submission->submission_type === OperationalSubmission::TYPE_MORTALITY) {
            return view('operational.submissions.mortality-create', [
                'batches' => Batch::submissionEligible()
                    ->with('scionVariety')
                    ->orderBy('batch_code')
                    ->get(),
                'causes' => MortalityRecord::CAUSES,
                'resubmissionOf' => $submission,
            ]);
        }

        if ($submission->submission_type === OperationalSubmission::TYPE_STOCK_OUT) {
            return view('operational.submissions.stock-out-create', [
                'clients' => Client::active()->orderBy('client_name')->get(),
                'batches' => Batch::submissionEligible()
                    ->with('scionVariety')
                    ->orderBy('batch_code')
                    ->get(),
                'units' => StockOutDetail::UNITS,
                'resubmissionOf' => $submission,
            ]);
        }

        if ($submission->submission_type === OperationalSubmission::TYPE_BATCH_CREATION) {
            abort_unless(SubmissionWorkflowSwitch::batchCreationEnabled(), 404);

            return view('operational.submissions.batch-creation-create', $this->batchCreationFormData($submission));
        }

        return view('operational.submissions.stock-in-create', $this->stockInFormData($submission));
    }

    public function storeMortalityResubmission(
        StoreMortalitySubmissionRequest $request,
        OperationalSubmission $submission,
    ): RedirectResponse {
        $this->assertResubmittable($request, $submission, OperationalSubmission::TYPE_MORTALITY);

        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh('mortality_resubmission', $validated, 'submission_mortality');

        try {
            $newSubmission = $this->submissions->mortality($validated, $request->user(), $submission);
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('mortality_resubmission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.show', $newSubmission)
            ->with('success', 'Mortality resubmitted for Manager verification.');
    }

    public function storeStockOutResubmission(
        StoreStockOutSubmissionRequest $request,
        OperationalSubmission $submission,
    ): RedirectResponse {
        $this->assertResubmittable($request, $submission, OperationalSubmission::TYPE_STOCK_OUT);

        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh('stock_out_resubmission', $validated, 'submission_stockout');

        try {
            $newSubmission = $this->submissions->stockOut($validated, $request->user(), $submission);
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_out_resubmission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.show', $newSubmission)
            ->with('success', 'Stock-out resubmitted for Manager verification.');
    }

    public function storeStockInResubmission(
        StoreStockInSubmissionRequest $request,
        OperationalSubmission $submission,
    ): RedirectResponse {
        $this->assertResubmittable($request, $submission, OperationalSubmission::TYPE_STOCK_IN);

        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh(
            'stock_in_resubmission',
            $request->duplicateFingerprintPayload(),
            'submission_stockin',
        );

        try {
            $newSubmission = $this->submissions->stockIn($validated, $request->user(), $submission);
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_in_resubmission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.show', $newSubmission)
            ->with('success', 'Stock-in resubmitted for Manager verification.');
    }

    private function assertResubmittable(Request $request, OperationalSubmission $submission, ?string $type = null): void
    {
        abort_unless((int) $submission->submitted_by_id === (int) $request->user()->id, 404);
        abort_unless($submission->isRejected(), 404);
        abort_unless($type === null || $submission->submission_type === $type, 404);
    }

    public function storeMortality(StoreMortalitySubmissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh('mortality_submission', $validated, 'submission_mortality');

        try {
            $this->submissions->mortality($validated, Auth::user());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('mortality_submission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.index')
            ->with('success', 'Mortality submitted for Manager verification.');
    }

    public function createStockIn(): View
    {
        return view('operational.submissions.stock-in-create', $this->stockInFormData());
    }

    public function storeStockIn(StoreStockInSubmissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh(
            'stock_in_submission',
            $request->duplicateFingerprintPayload(),
            'submission_stockin',
        );

        try {
            $this->submissions->stockIn($validated, Auth::user());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_in_submission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.index')
            ->with('success', 'Stock-in submitted for Manager verification.');
    }

    public function createStockOut(): View
    {
        return view('operational.submissions.stock-out-create', [
            'clients' => Client::active()->orderBy('client_name')->get(),
            'batches' => Batch::submissionEligible()
                ->with('scionVariety')
                ->orderBy('batch_code')
                ->get(),
            'units' => StockOutDetail::UNITS,
        ]);
    }

    public function storeStockOut(StoreStockOutSubmissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh('stock_out_submission', $validated, 'submission_stockout');

        try {
            $this->submissions->stockOut($validated, Auth::user());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_out_submission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.index')
            ->with('success', 'Stock-out submitted for Manager verification.');
    }

    public function createBatchCreation(): View
    {
        return view('operational.submissions.batch-creation-create', $this->batchCreationFormData());
    }

    public function storeBatchCreation(StoreBatchCreationSubmissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh(
            'batch_creation_submission',
            $validated,
            'submission_batch',
        );

        try {
            $this->submissions->batchCreation($validated, Auth::user());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('batch_creation_submission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.index')
            ->with('success', 'Batch creation submitted for Manager verification.');
    }

    public function storeBatchCreationResubmission(
        StoreBatchCreationSubmissionRequest $request,
        OperationalSubmission $submission,
    ): RedirectResponse {
        $this->assertResubmittable($request, $submission, OperationalSubmission::TYPE_BATCH_CREATION);

        $validated = $request->validated();
        $fingerprint = DuplicateSubmissionGuard::assertFresh(
            'batch_creation_resubmission',
            $validated,
            'submission_batch',
        );

        try {
            $newSubmission = $this->submissions->batchCreation($validated, $request->user(), $submission);
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('batch_creation_resubmission', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('operational.submissions.show', $newSubmission)
            ->with('success', 'Batch creation resubmitted for Manager verification.');
    }

    /**
     * @return array<string, mixed>
     */
    private function batchCreationFormData(?OperationalSubmission $resubmissionOf = null): array
    {
        return [
            'seedlingsAvailable' => StockBalances::seedlingsAvailable(),
            'receivedScionVarieties' => ScionVariety::query()
                ->receivedInStockIn()
                ->withScionAvailability()
                ->orderBy('scion_variety_name')
                ->get(),
            'activeScionVarieties' => ScionVariety::query()
                ->active()
                ->withScionAvailability()
                ->orderBy('scion_variety_name')
                ->get(),
            'suppliers' => Supplier::active()->orderBy('supplier_name')->get(),
            'resubmissionOf' => $resubmissionOf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockInFormData(?OperationalSubmission $resubmissionOf = null): array
    {
        return [
            'suppliers' => Supplier::active()->orderBy('supplier_name')->get(),
            'scionVarieties' => ScionVariety::query()->active()->orderBy('scion_variety_name')->get(),
            'resubmissionOf' => $resubmissionOf,
        ];
    }
}
