<?php

namespace App\Modules\MeetingManagement\Http\Requests;

use App\Modules\MeetingManagement\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('meetings.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(Meeting::KINDS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'template_id' => ['nullable', 'integer', 'exists:meeting_templates,id'],
            'recurrence_rule' => ['nullable', Rule::in(Meeting::RECURRENCE)],
            'recurrence_until' => ['nullable', 'date', 'after:starts_at'],
            'participants' => ['array'],
            'participants.*.user_id' => ['required_with:participants', 'integer', 'exists:users,id'],
            'participants.*.role' => ['nullable', 'string'],
            'agenda_items' => ['array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:200'],
            'agenda_items.*.description' => ['nullable', 'string', 'max:2000'],
            'agenda_items.*.time_allocation_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'agenda_items.*.presenter_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
