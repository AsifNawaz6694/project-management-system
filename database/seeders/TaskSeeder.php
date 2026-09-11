<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $employee = User::where('email', 'yousuf@bargoventures.com')->first();
        $manager = User::where('email', 'saad@bargoventures.com')->first();
        $admin = User::where('email', 'asif@bargoventures.com')->first();
        $byEmail = User::query()->whereIn('email', [
            'sameer@bargoventures.com', 'usman@bargoventures.com', 'ashar@bargoventures.com', 'adil@bargoventures.com',
            'muneeb@bargoventures.com', 'hania@bargoventures.com', 'raheel@bargoventures.com', 'marcin@bargoventures.com',
        ])->get()->keyBy('email');
        $u = fn (string $email) => $byEmail->get($email);

        if (! $employee || ! $manager) {
            return;
        }

        $tasks = [
            'mobile-app-redesign' => [
                ['title' => 'Audit existing screen inventory', 'status' => 'completed', 'priority' => 'medium', 'assignee' => $u('sameer@bargoventures.com'), 'days' => -22],
                ['title' => 'Define design tokens & color palette', 'status' => 'completed', 'priority' => 'high', 'assignee' => $u('sameer@bargoventures.com'), 'days' => -16],
                ['title' => 'Wire up new navigation pattern', 'status' => 'in_progress', 'priority' => 'high', 'assignee' => $u('muneeb@bargoventures.com'), 'days' => 4, 'subtasks' => [
                    ['title' => 'Tab bar prototype', 'completed' => true],
                    ['title' => 'Animation timing review', 'completed' => false],
                    ['title' => 'Accessibility pass on focus order', 'completed' => false],
                ]],
                ['title' => 'Profile screen polish', 'status' => 'in_progress', 'priority' => 'medium', 'assignee' => $employee, 'days' => 6],
                ['title' => 'Performance regression sweep', 'status' => 'todo', 'priority' => 'critical', 'assignee' => $u('ashar@bargoventures.com'), 'days' => 14],
                ['title' => 'Beta release notes draft', 'status' => 'todo', 'priority' => 'low', 'assignee' => $u('hania@bargoventures.com'), 'days' => 22],
            ],
            'q3-marketing-site-launch' => [
                ['title' => 'Final hero copy', 'status' => 'completed', 'priority' => 'medium', 'assignee' => $u('hania@bargoventures.com'), 'days' => -8],
                ['title' => 'Build marketing components in storybook', 'status' => 'in_progress', 'priority' => 'high', 'assignee' => $u('muneeb@bargoventures.com'), 'days' => 5],
                ['title' => 'SEO meta sweep', 'status' => 'todo', 'priority' => 'medium', 'assignee' => $u('marcin@bargoventures.com'), 'days' => 18],
                ['title' => 'Plan launch announcement', 'status' => 'todo', 'priority' => 'low', 'assignee' => $u('marcin@bargoventures.com'), 'days' => 30],
            ],
            'internal-reporting-pipeline' => [
                ['title' => 'Stakeholder requirements doc', 'status' => 'completed', 'priority' => 'high', 'assignee' => $manager, 'days' => -3],
                ['title' => 'Spike: warehouse vs. lake', 'status' => 'todo', 'priority' => 'high', 'assignee' => $u('usman@bargoventures.com'), 'days' => 12],
                ['title' => 'ETL job scheduler design', 'status' => 'todo', 'priority' => 'medium', 'assignee' => $u('adil@bargoventures.com'), 'days' => 20],
            ],
            'customer-support-knowledge-base' => [
                ['title' => 'Article taxonomy approval', 'status' => 'completed', 'priority' => 'medium', 'assignee' => $u('hania@bargoventures.com'), 'days' => -40],
                ['title' => 'Top 10 article drafts', 'status' => 'completed', 'priority' => 'high', 'assignee' => $u('hania@bargoventures.com'), 'days' => -18],
                ['title' => 'Search relevance tuning', 'status' => 'in_progress', 'priority' => 'medium', 'assignee' => $u('muneeb@bargoventures.com'), 'days' => 5],
                ['title' => 'Embed help widget on dashboard', 'status' => 'todo', 'priority' => 'medium', 'assignee' => $employee, 'days' => 12],
            ],
            'soc-2-readiness' => [
                ['title' => 'Document access review process', 'status' => 'in_progress', 'priority' => 'critical', 'assignee' => $u('raheel@bargoventures.com'), 'days' => 25],
                ['title' => 'Vendor risk assessment template', 'status' => 'todo', 'priority' => 'high', 'assignee' => $manager, 'days' => 50],
            ],
            'onboarding-refresh-v-2' => [
                ['title' => 'Final QA pass', 'status' => 'completed', 'priority' => 'high', 'assignee' => $u('ashar@bargoventures.com'), 'days' => -25],
                ['title' => 'Ship to 100% rollout', 'status' => 'completed', 'priority' => 'critical', 'assignee' => $u('muneeb@bargoventures.com'), 'days' => -20],
                ['title' => 'Retrospective notes', 'status' => 'completed', 'priority' => 'low', 'assignee' => $manager, 'days' => -15],
            ],
            'performance-tuning-sprint' => [
                ['title' => 'Establish latency baseline', 'status' => 'todo', 'priority' => 'high', 'assignee' => $u('raheel@bargoventures.com'), 'days' => 22],
                ['title' => 'Cache hit rate audit', 'status' => 'todo', 'priority' => 'medium', 'assignee' => $u('usman@bargoventures.com'), 'days' => 26],
            ],
        ];

        foreach ($tasks as $slug => $rows) {
            $project = Project::where('slug', $slug)->first();
            if (! $project) {
                continue;
            }

            $columnPositions = ['todo' => 0, 'in_progress' => 0, 'completed' => 0];

            foreach ($rows as $row) {
                $assignee = $row['assignee'] ?? null;
                $task = Task::create([
                    'project_id' => $project->id,
                    'created_by_id' => ($manager ?? $admin)?->id,
                    'assignee_id' => $assignee?->id,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'due_date' => now()->addDays($row['days'])->toDateString(),
                    'position' => ++$columnPositions[$row['status']],
                    'completed_at' => $row['status'] === 'completed' ? now()->addDays($row['days']) : null,
                ]);

                foreach ($row['subtasks'] ?? [] as $i => $sub) {
                    Task::create([
                        'project_id' => $project->id,
                        'parent_task_id' => $task->id,
                        'created_by_id' => ($manager ?? $admin)?->id,
                        'assignee_id' => $assignee?->id,
                        'title' => $sub['title'],
                        'status' => ! empty($sub['completed']) ? 'completed' : 'todo',
                        'priority' => $task->priority,
                        'position' => $i,
                        'completed_at' => ! empty($sub['completed']) ? now() : null,
                    ]);
                }

                if ($row['status'] === 'in_progress' && $manager) {
                    $task->comments()->create([
                        'user_id' => $manager->id,
                        'body' => 'Bumped this up to the top of the queue — let me know if anything is blocking.',
                    ]);
                    if ($assignee) {
                        $task->comments()->create([
                            'user_id' => $assignee->id,
                            'body' => 'Got it. I should have a draft ready by end of week.',
                        ]);
                    }
                }
            }
        }
    }
}
