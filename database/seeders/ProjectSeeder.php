<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'asif@bargoventures.com')->first();
        $manager = User::where('email', 'saad@bargoventures.com')->first();
        $employee = User::where('email', 'yousuf@bargoventures.com')->first();
        $mona = User::where('email', 'marcin@bargoventures.com')->first();

        if (! $admin || ! $manager || ! $employee) {
            return;
        }

        $byEmail = User::query()->whereIn('email', [
            'sameer@bargoventures.com', 'usman@bargoventures.com', 'ashar@bargoventures.com', 'adil@bargoventures.com',
            'muneeb@bargoventures.com', 'hania@bargoventures.com', 'raheel@bargoventures.com',
        ])->get()->keyBy('email');

        $u = fn (string $email) => $byEmail->get($email);

        $projects = [
            [
                'title' => 'Mobile App Redesign',
                'description' => 'Complete overhaul of the customer-facing mobile experience: new design system, performance budget, and accessibility audits across iOS and Android.',
                'status' => 'active',
                'priority' => 'high',
                'color' => 'violet',
                'progress' => 62,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(45)->toDateString(),
                'owner' => $manager,
                'members' => [$employee, $u('sameer@bargoventures.com'), $u('muneeb@bargoventures.com'), $u('ashar@bargoventures.com')],
                'milestones' => [
                    ['title' => 'Discovery & user research', 'completed' => true, 'days' => -25],
                    ['title' => 'Information architecture', 'completed' => true, 'days' => -18],
                    ['title' => 'High-fidelity UI for core flows', 'completed' => true, 'days' => -8],
                    ['title' => 'Engineering implementation', 'completed' => false, 'days' => 14],
                    ['title' => 'Beta release & feedback', 'completed' => false, 'days' => 32],
                    ['title' => 'Production rollout', 'completed' => false, 'days' => 45],
                ],
            ],
            [
                'title' => 'Q3 Marketing Site Launch',
                'description' => 'Brand new marketing site with refreshed messaging, case studies, and a programmatic SEO engine for the help center.',
                'status' => 'active',
                'priority' => 'medium',
                'color' => 'pink',
                'progress' => 38,
                'start_date' => now()->subDays(15)->toDateString(),
                'end_date' => now()->addDays(40)->toDateString(),
                'owner' => $mona ?? $manager,
                'members' => array_filter([$mona, $u('sameer@bargoventures.com'), $u('hania@bargoventures.com'), $u('muneeb@bargoventures.com')]),
                'milestones' => [
                    ['title' => 'Content audit & messaging', 'completed' => true, 'days' => -12],
                    ['title' => 'Visual design', 'completed' => true, 'days' => -4],
                    ['title' => 'Frontend build', 'completed' => false, 'days' => 18],
                    ['title' => 'SEO + analytics wiring', 'completed' => false, 'days' => 30],
                    ['title' => 'Launch', 'completed' => false, 'days' => 40],
                ],
            ],
            [
                'title' => 'Internal Reporting Pipeline',
                'description' => 'Replace ad-hoc spreadsheets with a unified reporting pipeline backed by warehoused data. Includes nightly ETL and dashboards.',
                'status' => 'planning',
                'priority' => 'high',
                'color' => 'blue',
                'progress' => 8,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(90)->toDateString(),
                'owner' => $manager,
                'members' => array_filter([$u('usman@bargoventures.com'), $u('adil@bargoventures.com'), $u('raheel@bargoventures.com')]),
                'milestones' => [
                    ['title' => 'Stakeholder requirements', 'completed' => true, 'days' => -2],
                    ['title' => 'Data model & warehouse', 'completed' => false, 'days' => 25],
                    ['title' => 'ETL & pipeline scheduling', 'completed' => false, 'days' => 50],
                    ['title' => 'Dashboards & access controls', 'completed' => false, 'days' => 75],
                    ['title' => 'Rollout & training', 'completed' => false, 'days' => 90],
                ],
            ],
            [
                'title' => 'Customer Support Knowledge Base',
                'description' => 'Self-serve help center with categorized articles, search, and analytics on what users actually look for.',
                'status' => 'active',
                'priority' => 'medium',
                'color' => 'emerald',
                'progress' => 74,
                'start_date' => now()->subDays(50)->toDateString(),
                'end_date' => now()->addDays(20)->toDateString(),
                'owner' => $manager,
                'members' => array_filter([$u('hania@bargoventures.com'), $u('muneeb@bargoventures.com'), $employee]),
                'milestones' => [
                    ['title' => 'Article taxonomy', 'completed' => true, 'days' => -45],
                    ['title' => 'CMS & search infra', 'completed' => true, 'days' => -25],
                    ['title' => 'Top 50 articles drafted', 'completed' => true, 'days' => -10],
                    ['title' => 'Public beta', 'completed' => false, 'days' => 8],
                    ['title' => 'Public launch', 'completed' => false, 'days' => 20],
                ],
            ],
            [
                'title' => 'SOC 2 Readiness',
                'description' => 'Implement controls and evidence collection across infrastructure, access management, and incident response in preparation for SOC 2 Type II.',
                'status' => 'on_hold',
                'priority' => 'critical',
                'color' => 'amber',
                'progress' => 22,
                'start_date' => now()->subDays(60)->toDateString(),
                'end_date' => now()->addDays(120)->toDateString(),
                'owner' => $admin,
                'members' => array_filter([$manager, $u('raheel@bargoventures.com'), $u('usman@bargoventures.com')]),
                'milestones' => [
                    ['title' => 'Gap analysis', 'completed' => true, 'days' => -50],
                    ['title' => 'Access management policies', 'completed' => false, 'days' => 25],
                    ['title' => 'Vendor risk reviews', 'completed' => false, 'days' => 60],
                    ['title' => 'Audit fieldwork', 'completed' => false, 'days' => 100],
                    ['title' => 'Final report', 'completed' => false, 'days' => 120],
                ],
            ],
            [
                'title' => 'Onboarding Refresh v2',
                'description' => 'New welcome experience for first-time users — checklist, guided tour, and contextual tips wired to product analytics.',
                'status' => 'completed',
                'priority' => 'medium',
                'color' => 'sky',
                'progress' => 100,
                'start_date' => now()->subDays(110)->toDateString(),
                'end_date' => now()->subDays(20)->toDateString(),
                'owner' => $manager,
                'members' => array_filter([$u('sameer@bargoventures.com'), $u('muneeb@bargoventures.com'), $employee]),
                'milestones' => [
                    ['title' => 'Onboarding research', 'completed' => true, 'days' => -100],
                    ['title' => 'Design + prototyping', 'completed' => true, 'days' => -80],
                    ['title' => 'Implementation', 'completed' => true, 'days' => -45],
                    ['title' => 'A/B test', 'completed' => true, 'days' => -28],
                    ['title' => 'Ship to 100% of new users', 'completed' => true, 'days' => -20],
                ],
            ],
            [
                'title' => 'Performance Tuning Sprint',
                'description' => 'Two-week focused effort to bring p95 latency under 250ms across the dashboard surfaces.',
                'status' => 'planning',
                'priority' => 'low',
                'color' => 'slate',
                'progress' => 0,
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(34)->toDateString(),
                'owner' => $manager,
                'members' => array_filter([$u('usman@bargoventures.com'), $u('raheel@bargoventures.com')]),
                'milestones' => [
                    ['title' => 'Baseline measurement', 'completed' => false, 'days' => 22],
                    ['title' => 'Top-10 query optimization', 'completed' => false, 'days' => 28],
                    ['title' => 'Cache + CDN tuning', 'completed' => false, 'days' => 32],
                    ['title' => 'Validation & rollout', 'completed' => false, 'days' => 34],
                ],
            ],
        ];

        foreach ($projects as $cfg) {
            $project = Project::updateOrCreate(
                ['title' => $cfg['title']],
                [
                    'description' => $cfg['description'],
                    'status' => $cfg['status'],
                    'priority' => $cfg['priority'],
                    'color' => $cfg['color'],
                    'progress' => $cfg['progress'],
                    'start_date' => $cfg['start_date'],
                    'end_date' => $cfg['end_date'],
                    'owner_id' => $cfg['owner']?->id,
                ],
            );

            $sync = [];
            $owner = $cfg['owner'];
            if ($owner) {
                $sync[$owner->id] = ['role' => 'owner', 'joined_at' => now()];
            }
            foreach ($cfg['members'] as $m) {
                if ($m && ! isset($sync[$m->id])) {
                    $sync[$m->id] = ['role' => 'member', 'joined_at' => now()];
                }
            }
            $project->members()->sync($sync);

            $project->milestones()->delete();
            foreach (array_values($cfg['milestones']) as $i => $m) {
                $project->milestones()->create([
                    'title' => $m['title'],
                    'due_date' => now()->addDays($m['days'])->toDateString(),
                    'completed_at' => $m['completed'] ? now()->addDays($m['days']) : null,
                    'position' => $i,
                ]);
            }
        }
    }
}
