<?php

namespace App\Http\Requests;

class RejectOperationalSubmissionRequest extends AppFormRequest
{
    protected $errorBag = 'submission_rejection';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A written reason is required to reject a submission.',
            'reason.max' => 'The rejection reason may not be greater than 2,000 characters.',
        ];
    }
}
