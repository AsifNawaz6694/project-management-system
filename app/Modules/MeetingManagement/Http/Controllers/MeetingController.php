<?php

namespace App\Modules\MeetingManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MeetingManagement\Http\Requests\StoreMeetingRequest;
use App\Modules\MeetingManagement\Http\Requests\UpdateMeetingRequest;
use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\MeetingManagement\Models\MeetingTemplate;
use App\Modules\MeetingManagement\Services\MeetingService;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $view = $request->string('view')->toString() ?: 'upcoming';

        $base = Meeting::query()
            ->visibleTo($user)
            ->with(['organizer:id,name,avatar', 'participants:id,name,avatar', 'project:id,slug,title,color'])
            ->withCount(['agendaItems', 'actionItems']);

        $base = match ($view) {
            'past' => $base->past(),
            'mine' => $base->where('organizer_id', $user->id)->orderByDesc('starts_at'),
            default => $base->upcoming(),
        };

        return Inertia::render('meetings/index', [
            'meetings' => $base->limit(100)->get(),
            'view' => $view,
            'kinds' => Meeting::KINDS,
            'stats' => [
                'upcoming' => Meeting::visibleTo($user)->where('starts_at', '>=', now())->count(),
                'past' => Meeting::visibleTo($user)->where('starts_at', '<', now())->count(),
                'mine' => Meeting::where('organizer_id', $user->id)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('meetings/create', [
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color']),
            'people' => $this->people(),
            'templates' => MeetingTemplate::orderBy('name')->get(['id', 'name', 'kind', 'description', 'agenda_items']),
            'kinds' => Meeting::KINDS,
            'recurrence' => Meeting::RECURRENCE,
        ]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $meeting = $this->service->create($request->validated(), $request->user());

        return redirect()->route('meetings.show', $meeting)->with('status', 'Meeting scheduled.');
    }

    public function show(Request $request, Meeting $meeting): Response
    {
        $user = $request->user();
        if (! Meeting::visibleTo($user)->whereKey($meeting->id)->exists()) {
            abort(403);
        }

        $meeting->load([
            'organizer:id,name,avatar,job_title',
            'project:id,slug,title,color',
            'participants:id,name,avatar,job_title',
            'agendaItems.presenter:id,name,avatar',
            'actionItems.assignee:id,name,avatar',
            'actionItems.creator:id,name,avatar',
            'actionItems.task:id,title,status',
        ]);

        $series = $meeting->series_id
            ? Meeting::where('series_id', $meeting->series_id)
                ->orderBy('starts_at')
                ->get(['id', 'title', 'starts_at', 'status'])
            : collect();

        return Inertia::render('meetings/show', [
            'meeting' => $meeting,
            'series' => $series,
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title']),
            'people' => $this->people(),
            'canEdit' => $user->hasPermission('meetings.manage') || $meeting->organizer_id === $user->id,
            'canDelete' => $user->hasPermission('meetings.delete') || $meeting->organizer_id === $user->id,
        ]);
    }

    public function edit(Request $request, Meeting $meeting): Response
    {
        $this->authorizeEdit($request, $meeting);
        $meeting->load(['agendaItems', 'participants:id,name,avatar']);

        return Inertia::render('meetings/edit', [
            'meeting' => $meeting,
            'projects' => Project::visibleTo($request->user())->orderBy('title')->get(['id', 'slug', 'title']),
            'people' => $this->people(),
            'kinds' => Meeting::KINDS,
        ]);
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->service->update($meeting, $request->validated(), $request->user());

        return redirect()->route('meetings.show', $meeting)->with('status', 'Meeting updated.');
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorizeEdit($request, $meeting);
        $this->service->delete($meeting);

        return redirect()->route('meetings.index')->with('status', 'Meeting cancelled.');
    }

    public function saveNotes(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        if (! Meeting::visibleTo($user)->whereKey($meeting->id)->exists()) {
            abort(403);
        }

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:50000']]);
        $this->service->saveNotes($meeting, $data['notes'] ?? null, $user);

        return back()->with('status', 'Notes saved.');
    }

    public function rsvp(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate(['rsvp_status' => ['required', 'in:accepted,declined,tentative']]);
        $meeting->participants()->updateExistingPivot($request->user()->id, [
            'rsvp_status' => $data['rsvp_status'],
            'responded_at' => now(),
        ]);

        return back();
    }

    private function authorizeEdit(Request $request, Meeting $meeting): void
    {
        $user = $request->user();
        if (! ($user->hasPermission('meetings.manage') || $meeting->organizer_id === $user->id)) {
            abort(403);
        }
    }

    private function people()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title']),
                'initials' => $u->initials,
            ]);
    }
}
