<?php

namespace App\Modules\Feedback\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Feedback\Models\FeedbackRequest;
use App\Modules\Feedback\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackRequestController extends Controller
{
    public function __construct(private readonly FeedbackService $service) {}

    public function show(Request $request, FeedbackRequest $feedbackRequest): Response
    {
        $user = $request->user();
        if ($feedbackRequest->reviewer_id !== $user->id && ! $user->hasPermission('feedback.manage')) {
            abort(403);
        }

        $feedbackRequest->load([
            'cycle.questions',
            'subject:id,name,avatar,job_title',
            'responses',
        ]);

        return Inertia::render('feedback/respond', [
            'request' => $feedbackRequest,
        ]);
    }

    public function submit(Request $request, FeedbackRequest $feedbackRequest): RedirectResponse
    {
        $user = $request->user();
        if ($feedbackRequest->reviewer_id !== $user->id) {
            abort(403);
        }

        $payload = $request->validate([
            'responses' => ['required', 'array'],
            'responses.*.answer' => ['nullable', 'string', 'max:5000'],
            'responses.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $this->service->submitResponses($feedbackRequest, $payload['responses'], $user);

        return redirect()->route('feedback.cycles.index')->with('status', 'Feedback submitted.');
    }

    public function decline(Request $request, FeedbackRequest $feedbackRequest): RedirectResponse
    {
        if ($feedbackRequest->reviewer_id !== $request->user()->id) {
            abort(403);
        }
        $this->service->decline($feedbackRequest);

        return redirect()->route('feedback.cycles.index')->with('status', 'Request declined.');
    }
}
