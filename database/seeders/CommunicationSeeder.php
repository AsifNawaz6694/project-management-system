<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Communication\Models\ProjectComment;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Seeder;

class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@raqtan.com')->first();
        $manager = User::where('email', 'manager@raqtan.com')->first();
        $employee = User::where('email', 'employee@raqtan.com')->first();
        $yara = User::where('email', 'yara@raqtan.com')->first();
        $bilal = User::where('email', 'bilal@raqtan.com')->first();

        $threads = [
            [
                'project' => 'mobile-app-redesign',
                'comments' => [
                    ['user' => $manager, 'body' => 'Heads up team — kicking off the polish phase next week. @Hamza Ali please make sure the profile screen mocks are exported by Thursday.'],
                    ['user' => $employee, 'body' => 'On it @Sarah Mansoor. Will share the export link in #design later today.'],
                    ['user' => $yara, 'body' => 'Should we also book a quick sync to align on copy? I have a few open questions.'],
                ],
            ],
            [
                'project' => 'q3-marketing-site-launch',
                'comments' => [
                    ['user' => $manager, 'body' => "Storybook setup is in. @Bilal Tariq if you can review the navigation primitives by tomorrow that'd unblock the build phase."],
                    ['user' => $bilal, 'body' => "Noted. I'll leave inline notes by EOD."],
                ],
            ],
            [
                'project' => 'soc-2-readiness',
                'comments' => [
                    ['user' => $admin, 'body' => 'We need to formalize the access review cadence. Proposal: monthly automated review with @Sarah Mansoor sign-off.'],
                ],
            ],
        ];

        foreach ($threads as $thread) {
            $project = Project::where('slug', $thread['project'])->first();
            if (! $project) {
                continue;
            }

            $parentId = null;
            foreach ($thread['comments'] as $i => $row) {
                if (! $row['user']) {
                    continue;
                }

                $body = $row['body'];
                preg_match_all('/@([A-Za-z]+(?:\s[A-Za-z]+)?)/', $body, $matches);
                $mentionIds = User::query()->whereIn('name', $matches[1])->pluck('id')->all();

                $comment = ProjectComment::create([
                    'project_id' => $project->id,
                    'user_id' => $row['user']->id,
                    'parent_id' => $i === 0 ? null : $parentId,
                    'body' => $body,
                    'mentions' => $mentionIds,
                    'created_at' => now()->subDays(count($thread['comments']) - $i)->subHours(rand(0, 12)),
                ]);

                if ($i === 0) {
                    $parentId = $comment->id;
                }
            }
        }
    }
}
