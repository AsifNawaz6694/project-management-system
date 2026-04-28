<?php

namespace App\Modules\MeetingManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MeetingManagement\Http\Requests\StoreActionItemRequest;
use App\Modules\MeetingManagement\Models\ActionItem;
use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\MeetingManagement\Services\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    public function __construct(private readonly MeetingService $service) {}

    public function store(StoreActionItemRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->service->addActionItem($meeting, $request->validated(), $request->user());

        return back()->with('status', 'Action item added.');
    }

    public function update(Request $request, Meeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        if ($actionItem->meeting_id !== $meeting->id) {
            abort(404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:open,in_progress,done,cancelled'],
        ]);

        $this->service->updateActionItem($actionItem, $data);

        return back()->with('status', 'Action item updated.');
    }

    public function destroy(Request $request, Meeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        if ($actionItem->meeting_id !== $meeting->id) {
            abort(404);
        }
        $user = $request->user();
        if ($actionItem->created_by_id !== $user->id
            && $meeting->organizer_id !== $user->id
            && ! $user->hasPermission('meetings.manage')) {
            abort(403);
        }

        $this->service->deleteActionItem($actionItem);

        return back()->with('status', 'Action item removed.');
    }

    public function convertToTask(Request $request, Meeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        if ($actionItem->meeting_id !== $meeting->id) {
            abort(404);
        }

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $task = $this->service->convertActionItemToTask($actionItem, (int) $data['project_id'], $request->user());

        return redirect()->route('tasks.show', $task)->with('status', 'Action item promoted to a task.');
    }
}
