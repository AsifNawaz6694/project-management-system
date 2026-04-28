<?php

namespace App\Modules\MeetingManagement\Http\Requests;

use App\Modules\MeetingManagement\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

class StoreActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');
        $user = $this->user();
        if (! $user || ! $meeting instanceof Meeting) {
            return false;
        }

        return Meeting::visibleTo($user)->whereKey($meeting->id)->exists();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'agenda_item_id' => ['nullable', 'integer', 'exists:agenda_items,id'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
