<?php

namespace App\Http\Controllers;

use App\Actions\ApproveOperationalSubmission;
use App\Actions\RejectOperationalSubmission;
use App\Actions\SubmitOperationalSubmission;
use App\Exceptions\ApprovalRevalidationException;
use App\Http\Requests\RejectOperationalSubmissionRequest;
use App\Http\Requests\StoreMortalitySubmissionRequest;
use App\Http\Requests\StoreStockInSubmissionRequest;
use App\Http\Requests\StoreStockOutSubmissionRequest;
use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\OperationalSubmission;
use App\Models\ScionVariety;
use App\Models\StockOutDetail;
use App\Models\Supplier;
use App\Support\DuplicateSubmissionGuard;
use App\Support\OperationalSubmissionVisibility;
use App\Support\SubmissionWorkflowSwitch;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $submissions = OperationalSubmissionVisibility::scope(
            OperationalSubmission::query()->with(['submitter', 'subjectBatch', 'decider', 'resubmissionOf']),
            $user,
        )
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->paginate(10)
            ->withQueryString();

        return view('operational.submissions.index', [
            'submissions' => $submissions,
            'canSubmit' => $user->hasPermission('submit_operations'),
            'canVerify' => $user->hasPermission('verify_operations'),
            'canViewAll' => OperationalSubmissionVisibility::canViewAll($user),
            'stockInSubmissionEnabled' => SubmissionWorkflowSwitch::stockInEnabled(),
        ]);
    }

    public function queue(Request $request): View
    {
        $submissions = OperationalSubmission::query()
            ->with(['submitter', 'subjectBatch'])
            ->where('status', OperationalSubmission::STATUS_PENDING)
            ->orderBy('submitted_at')
            ->orderBy('submission_id')
            ->paginate(10)
            ->withQueryString();

        return view('operational.submissions.queue', [
            'submissions' => $submissions,
        ]);
    }

    public function show(Request $request, OperationalSubmission $submission): View
    {
        $user = $request->user();

        abort_unless(OperationalSubmissionVisibility::canView($user, $submission), 404);

        $submission->load(['submitter', 'decider', 'subjectBatch', 'resubmissionOf', 'records']);

        $supplierIds = collect([
            $submission->payload['seedling']['supplier_id'] ?? null,
            $submission->payload['scion']['supplier_id'] ?? null,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $varietyIds = collect([
            $submission->payload['scion']['scion_variety_id'] ?? null,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return view('operational.submissions.show', [
            'submission' => $submission,
            'canVerify' => $user->hasPermission('verify_operations'),
            'canSubmit' => $user->hasPermission('submit_operations'),
            'canViewAll' => OperationalSubmissionVisibility::canViewAll($user),
            'stockInSubmissionEnabled' => SubmissionWorkflowSwitch::stockInEnabled(),
            'stockInSuppliers' => Supplier::query()
                ->whereIn('supplier_id', $supplierIds)
                ->pluck('supplier_name', 'supplier_id'),
            'stockInVarieties' => ScionVariety::query()
                ->whereIn('scion_variety_id', $varietyIds)
                ->pluck('scion_variety_name', 'scion_variety_id'),
        ]);
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

        abort(404);
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
