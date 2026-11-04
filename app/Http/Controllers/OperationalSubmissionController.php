<?php

namespace App\Http\Controllers;

use App\Actions\ApproveOperationalSubmission;
use App\Actions\SubmitOperationalSubmission;
use App\Http\Requests\StoreMortalitySubmissionRequest;
use App\Http\Requests\StoreStockOutSubmissionRequest;
use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\OperationalSubmission;
use App\Models\StockOutDetail;
use App\Support\DuplicateSubmissionGuard;
use App\Support\OperationalSubmissionVisibility;
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

        return view('operational.submissions.show', [
            'submission' => $submission,
            'canVerify' => $user->hasPermission('verify_operations'),
            'canSubmit' => $user->hasPermission('submit_operations'),
            'canViewAll' => OperationalSubmissionVisibility::canViewAll($user),
        ]);
    }

    public function approve(Request $request, OperationalSubmission $submission): RedirectResponse
    {
        try {
            $this->approvals->execute($submission, (int) $request->user()->id);
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
}
