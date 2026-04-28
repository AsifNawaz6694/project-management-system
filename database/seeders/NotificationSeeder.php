<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@raqtan.com')->first();
        $manager = User::where('email', 'manager@raqtan.com')->first();
        $employee = User::where('email', 'employee@raqtan.com')->first();

        if (! $admin || ! $manager || ! $employee) {
            return;
        }

        $rows = [
            [
                'user_id' => $manager->id,
                'actor_id' => $employee->id,
                'group' => 'expenses',
                'type' => 'expense.submitted',
                'title' => 'Hamza Ali submitted an expense',
                'body' => 'EXP-1003 · Team offsite lunch · USD 280.00',
                'icon' => 'wallet',
                'tone' => 'amber',
                'link' => '/expenses',
                'minutes_ago' => 8,
                'read' => false,
            ],
            [
                'user_id' => $manager->id,
                'actor_id' => $admin->id,
                'group' => 'mentions',
                'type' => 'comment.mention',
                'title' => 'You were mentioned',
                'body' => 'Talha Kayani mentioned you on project "SOC 2 Readiness"',
                'icon' => 'at-sign',
                'tone' => 'pink',
                'link' => '/projects/soc-2-readiness',
                'minutes_ago' => 35,
                'read' => false,
            ],
            [
                'user_id' => $manager->id,
                'actor_id' => null,
                'group' => 'deadlines',
                'type' => 'task.due-soon',
                'title' => 'Performance regression sweep is due in 3 days',
                'body' => 'Mobile App Redesign · Critical priority',
                'icon' => 'calendar-clock',
                'tone' => 'rose',
                'link' => '/tasks',
                'minutes_ago' => 60 * 4,
                'read' => true,
            ],
            [
                'user_id' => $employee->id,
                'actor_id' => $manager->id,
                'group' => 'tasks',
                'type' => 'task.assigned',
                'title' => 'Sarah Mansoor assigned a task to you',
                'body' => 'Profile screen polish',
                'icon' => 'list-checks',
                'tone' => 'violet',
                'link' => '/tasks',
                'minutes_ago' => 18,
                'read' => false,
            ],
            [
                'user_id' => $employee->id,
                'actor_id' => $manager->id,
                'group' => 'mentions',
                'type' => 'comment.mention',
                'title' => 'You were mentioned',
                'body' => 'Sarah Mansoor mentioned you on project "Mobile App Redesign"',
                'icon' => 'at-sign',
                'tone' => 'pink',
                'link' => '/projects/mobile-app-redesign',
                'minutes_ago' => 45,
                'read' => false,
            ],
            [
                'user_id' => $employee->id,
                'actor_id' => $manager->id,
                'group' => 'expenses',
                'type' => 'expense.approved',
                'title' => 'Sarah Mansoor approved your expense',
                'body' => 'EXP-1002 · User research participants',
                'icon' => 'check-circle-2',
                'tone' => 'emerald',
                'link' => '/expenses',
                'minutes_ago' => 60 * 24,
                'read' => true,
            ],
            [
                'user_id' => $admin->id,
                'actor_id' => $manager->id,
                'group' => 'projects',
                'type' => 'project.added',
                'title' => 'Sarah Mansoor added a new project',
                'body' => 'Performance Tuning Sprint',
                'icon' => 'folder-kanban',
                'tone' => 'blue',
                'link' => '/projects/performance-tuning-sprint',
                'minutes_ago' => 60 * 6,
                'read' => false,
            ],
            [
                'user_id' => $admin->id,
                'actor_id' => null,
                'group' => 'system',
                'type' => 'workspace.welcome',
                'title' => 'Welcome to Raqtan PMS',
                'body' => 'Your workspace is fully wired with projects, tasks, expenses, files, and notifications.',
                'icon' => 'sparkles',
                'tone' => 'violet',
                'link' => '/dashboard',
                'minutes_ago' => 60 * 24 * 2,
                'read' => true,
            ],
        ];

        foreach ($rows as $row) {
            Notification::create([
                'user_id' => $row['user_id'],
                'actor_id' => $row['actor_id'],
                'group' => $row['group'],
                'type' => $row['type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'icon' => $row['icon'],
                'tone' => $row['tone'],
                'link' => $row['link'],
                'read_at' => $row['read'] ? now()->subMinutes($row['minutes_ago'])->addMinute() : null,
                'created_at' => now()->subMinutes($row['minutes_ago']),
                'updated_at' => now()->subMinutes($row['minutes_ago']),
            ]);
        }
    }
}
