<?php

namespace App\Modules\Feedback\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Feedback\Http\Requests\StoreFeedbackCycleRequest;
use App\Modules\Feedback\Models\FeedbackCycle;
use App\Modules\Feedback\Models\FeedbackQuestion;
use App\Modules\Feedback\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackCycleController extends Controller
{
    public function __construct(private readonly FeedbackService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('feedback/index', [
            'cycles' => FeedbackCycle::query()
                ->visibleTo($user)
                ->with(['creator:id,name,avatar'])
                ->withCount(['questions', 'requests'])
                ->orderByDesc('starts_at')
                ->get(),
            'incoming' => $user->feedbackRequestsAsReviewer()
                ->with(['subject:id,name,avatar', 'cycle:id,name,kind,ends_at'])
                ->where('status', 'pending')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('feedback/create', [
            'people' => $this->people(),
            'kinds' => FeedbackCycle::KINDS,
            'statuses' => FeedbackCycle::STATUSES,
            'question_kinds' => FeedbackQuestion::KINDS,
        ]);
    }

    public function store(StoreFeedbackCycleRequest $request): RedirectResponse
    {
        $cycle = $this->service->createCycle($request->validated(), $request->user());

        return redirect()->route('feedback.cycles.show', $cycle)->with('status', 'Feedback cycle created.');
    }

    public function show(Request $request, FeedbackCycle $cycle): Response
    {
        $user = $request->user();
        if (! FeedbackCycle::visibleTo($user)->whereKey($cycle->id)->exists()) {
            abort(403);
        }

        $cycle->load([
            'creator:id,name,avatar',
            'questions',
            'requests.subject:id,name,avatar',
            'requests.reviewer:id,name,avatar',
        ]);

        return Inertia::render('feedback/show', [
            'cycle' => $cycle,
            'canManage' => $user->hasPermission('feedback.manage') || $cycle->created_by_id === $user->id,
        ]);
    }

    public function activate(Request $request, FeedbackCycle $cycle): RedirectResponse
    {
        $user = $request->user();
        if (! ($user->hasPermission('feedback.manage') || $cycle->created_by_id === $user->id)) {
            abort(403);
        }

        $this->service->activateCycle($cycle);

        return back()->with('status', 'Cycle activated and reviewers notified.');
    }

    public function close(Request $request, FeedbackCycle $cycle): RedirectResponse
    {
        $user = $request->user();
        if (! ($user->hasPermission('feedback.manage') || $cycle->created_by_id === $user->id)) {
            abort(403);
        }
        $cycle->update(['status' => 'closed']);

        return back()->with('status', 'Cycle closed.');
    }

    public function destroy(Request $request, FeedbackCycle $cycle): RedirectResponse
    {
        $user = $request->user();
        if (! ($user->hasPermission('feedback.manage') || $cycle->created_by_id === $user->id)) {
            abort(403);
        }
        $cycle->delete();

        return redirect()->route('feedback.cycles.index')->with('status', 'Cycle deleted.');
    }

    private function people()
    {
        return User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title']),
                'initials' => $u->initials,
            ]);
    }
}
