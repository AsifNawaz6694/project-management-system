<?php

namespace App\Modules\MeetingManagement\Http\Requests;

use App\Modules\MeetingManagement\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $meeting = $this->route('meeting');
        if (! $user || ! $meeting instanceof Meeting) {
            return false;
        }

        return $user->hasPermission('meetings.manage')
            || $meeting->organizer_id === $user->id;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'kind' => ['sometimes', 'required', Rule::in(Meeting::KINDS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'required', Rule::in(Meeting::STATUSES)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'participants' => ['sometimes', 'array'],
            'participants.*.user_id' => ['required_with:participants', 'integer', 'exists:users,id'],
            'participants.*.role' => ['nullable', 'string'],
            'agenda_items' => ['sometimes', 'array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:200'],
            'agenda_items.*.description' => ['nullable', 'string', 'max:2000'],
            'agenda_items.*.time_allocation_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
        ];
    }
}
