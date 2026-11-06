<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\OperationalSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a pending operational submission. This action never writes an
 * official operational row, balance, quantity or analytics value.
 */
class SubmitOperationalSubmission
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function mortality(array $data, User $submitter, ?OperationalSubmission $resubmissionOf = null): OperationalSubmission
    {
        $payload = [
            'batch_id' => (int) $data['batch_id'],
            'quantity' => (int) $data['quantity'],
            'cause' => (string) $data['cause'],
            'cause_other' => $data['cause_other'] ?? null,
            'recorded_at' => $data['recorded_at'],
        ];

        return $this->create(
            OperationalSubmission::TYPE_MORTALITY,
            $payload,
            $submitter,
            (int) $data['batch_id'],
            $resubmissionOf,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function stockOut(array $data, User $submitter, ?OperationalSubmission $resubmissionOf = null): OperationalSubmission
    {
        $details = collect($data['details'])
            ->map(fn (array $line): array => [
                'batch_id' => (int) $line['batch_id'],
                'quantity' => (int) $line['quantity'],
                'unit' => (string) $line['unit'],
                'unit_price' => isset($line['unit_price']) ? (float) $line['unit_price'] : null,
            ])
            ->values()
            ->all();

        $payload = [
            'client_id' => (int) $data['client_id'],
            'release_date' => $data['release_date'],
            'details' => $details,
        ];

        return $this->create(
            OperationalSubmission::TYPE_STOCK_OUT,
            $payload,
            $submitter,
            null,
            $resubmissionOf,
        );
    }

    /**
     * Capture one grouped Stock In request. Both populated tabs are frozen in
     * one payload; no official stock_ins row is written here.
     *
     * @param  array<string, mixed>  $data
     */
    public function stockIn(array $data, User $submitter, ?OperationalSubmission $resubmissionOf = null): OperationalSubmission
    {
        $payload = [];

        $seedling = $data['seedling'] ?? null;

        if (is_array($seedling)) {
            $payload['seedling'] = [
                'supplier_id' => (int) $seedling['supplier_id'],
                'quantity' => (int) $seedling['quantity'],
                'origin' => (string) $seedling['origin'],
                'notes' => $seedling['notes'] ?? null,
                'date_received' => $seedling['date_received'],
            ];
        }

        $scion = $data['scion'] ?? null;

        if (is_array($scion)) {
            $payload['scion'] = [
                'supplier_id' => isset($scion['supplier_id']) && $scion['supplier_id'] !== null
                    ? (int) $scion['supplier_id']
                    : null,
                'quantity' => (int) $scion['quantity'],
                'origin' => (string) $scion['origin'],
                'scion_variety_id' => (int) $scion['scion_variety_id'],
                'unit_price' => (float) $scion['unit_price'],
                'notes' => $scion['notes'] ?? null,
                'date_received' => $scion['date_received'],
            ];
        }

        return $this->create(
            OperationalSubmission::TYPE_STOCK_IN,
            $payload,
            $submitter,
            null,
            $resubmissionOf,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(
        string $type,
        array $payload,
        User $submitter,
        ?int $subjectBatchId,
        ?OperationalSubmission $resubmissionOf,
    ): OperationalSubmission {
        return DB::transaction(function () use ($type, $payload, $submitter, $subjectBatchId, $resubmissionOf): OperationalSubmission {
            $submission = OperationalSubmission::create([
                'submission_type' => $type,
                'status' => OperationalSubmission::STATUS_PENDING,
                'payload' => $payload,
                'submitted_by_id' => $submitter->id,
                'submitted_at' => now(),
                'resubmission_of_id' => $resubmissionOf?->submission_id,
                'subject_batch_id' => $subjectBatchId,
            ]);

            ActivityLog::record(
                $subjectBatchId,
                ActivityLog::TYPE_OPERATIONAL_SUBMISSION_SUBMITTED,
                $this->submittedDescription($type, $payload),
                $submitter->id,
            );

            return $submission;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submittedDescription(string $type, array $payload): string
    {
        if ($type === OperationalSubmission::TYPE_MORTALITY) {
            $batch = Batch::find($payload['batch_id']);

            return 'Submitted mortality for '.($batch?->batch_code ?? 'batch')." ({$payload['quantity']}).";
        }

        if ($type === OperationalSubmission::TYPE_STOCK_IN) {
            $parts = [];

            if (is_array($payload['seedling'] ?? null)) {
                $parts[] = 'Seedlings '.(int) ($payload['seedling']['quantity'] ?? 0);
            }

            if (is_array($payload['scion'] ?? null)) {
                $parts[] = 'Scion '.(int) ($payload['scion']['quantity'] ?? 0);
            }

            return 'Submitted stock-in ('.implode(' and ', $parts).').';
        }

        $client = Client::find($payload['client_id']);
        $lineCount = count($payload['details'] ?? []);

        return 'Submitted stock-out for '.($client?->client_name ?? 'client')." ({$lineCount} line(s)).";
    }
}
