<?php

namespace App\Modules\Feedback\Http\Requests;

use App\Modules\Feedback\Models\FeedbackCycle;
use App\Modules\Feedback\Models\FeedbackQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('feedback.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(FeedbackCycle::KINDS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(FeedbackCycle::STATUSES)],
            'anonymous' => ['boolean'],
            'questions' => ['array'],
            'questions.*.body' => ['required_with:questions', 'string', 'max:500'],
            'questions.*.kind' => ['nullable', Rule::in(FeedbackQuestion::KINDS)],
            'questions.*.required' => ['boolean'],
            'pairs' => ['array'],
            'pairs.*.subject_user_id' => ['required_with:pairs', 'integer', 'exists:users,id'],
            'pairs.*.reviewer_id' => ['required_with:pairs', 'integer', 'exists:users,id', 'different:pairs.*.subject_user_id'],
            'pairs.*.relationship' => ['nullable', 'string', 'max:50'],
        ];
    }
}
