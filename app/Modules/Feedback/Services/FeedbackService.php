<?php

namespace App\Modules\Feedback\Services;

use App\Models\User;
use App\Modules\Feedback\Models\FeedbackCycle;
use App\Modules\Feedback\Models\FeedbackQuestion;
use App\Modules\Feedback\Models\FeedbackRequest;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Support\Facades\DB;

class FeedbackService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function createCycle(array $data, User $creator): FeedbackCycle
    {
        return DB::transaction(function () use ($data, $creator) {
            $cycle = FeedbackCycle::create([
                'created_by_id' => $creator->id,
                'name' => $data['name'],
                'kind' => $data['kind'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'] ?? 'draft',
                'anonymous' => $data['anonymous'] ?? false,
            ]);

            foreach (array_values($data['questions'] ?? []) as $i => $q) {
                if (empty(trim((string) ($q['body'] ?? '')))) {
                    continue;
                }

                FeedbackQuestion::create([
                    'feedback_cycle_id' => $cycle->id,
                    'body' => $q['body'],
                    'kind' => $q['kind'] ?? 'text',
                    'required' => $q['required'] ?? true,
                    'position' => $i,
                ]);
            }

            foreach ($data['pairs'] ?? [] as $pair) {
                FeedbackRequest::firstOrCreate([
                    'feedback_cycle_id' => $cycle->id,
                    'subject_user_id' => $pair['subject_user_id'],
                    'reviewer_id' => $pair['reviewer_id'],
                ], [
                    'relationship' => $pair['relationship'] ?? null,
                    'status' => 'pending',
                ]);
            }

            Activity::log('feedback.cycle-created', [
                'module' => 'feedback',
                'description' => "Created feedback cycle \"{$cycle->name}\"",
                'properties' => ['cycle_id' => $cycle->id],
            ]);

            return $cycle->load(['questions', 'requests']);
        });
    }

    public function activateCycle(FeedbackCycle $cycle): void
    {
        DB::transaction(function () use ($cycle) {
            $cycle->update(['status' => 'active']);

            $cycle->requests()->with(['subject:id,name', 'cycle:id,name'])->get()->each(function (FeedbackRequest $r) use ($cycle) {
                $this->notifications->push((int) $r->reviewer_id, [
                    'group' => Notification::GROUP_SYSTEM,
                    'type' => 'feedback.requested',
                    'title' => 'Feedback requested',
                    'body' => "Share feedback on {$r->subject->name} for \"{$cycle->name}\"",
                    'icon' => 'message-square',
                    'tone' => 'pink',
                    'link' => "/feedback/requests/{$r->id}",
                    'data' => ['cycle_id' => $cycle->id, 'request_id' => $r->id],
                ]);
            });

            Activity::log('feedback.cycle-activated', [
                'module' => 'feedback',
                'description' => "Activated cycle \"{$cycle->name}\"",
                'properties' => ['cycle_id' => $cycle->id],
            ]);
        });
    }

    public function submitResponses(FeedbackRequest $request, array $responses, User $reviewer): void
    {
        DB::transaction(function () use ($request, $responses, $reviewer) {
            foreach ($responses as $questionId => $payload) {
                $request->responses()->updateOrCreate(
                    ['feedback_question_id' => (int) $questionId],
                    [
                        'answer' => $payload['answer'] ?? null,
                        'rating' => isset($payload['rating']) ? (int) $payload['rating'] : null,
                    ],
                );
            }

            $request->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            Activity::log('feedback.submitted', [
                'module' => 'feedback',
                'description' => 'Submitted feedback',
                'properties' => ['cycle_id' => $request->feedback_cycle_id, 'request_id' => $request->id, 'reviewer_id' => $reviewer->id],
            ]);
        });
    }

    public function decline(FeedbackRequest $request): void
    {
        $request->update(['status' => 'declined']);
        Activity::log('feedback.declined', [
            'module' => 'feedback',
            'description' => 'Declined a feedback request',
            'properties' => ['request_id' => $request->id, 'cycle_id' => $request->feedback_cycle_id],
        ]);
    }
}
