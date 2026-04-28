<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\ExpenseManagement\Models\Expense;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@raqtan.com')->first();
        $manager = User::where('email', 'manager@raqtan.com')->first();
        $employee = User::where('email', 'employee@raqtan.com')->first();
        $byEmail = User::query()->whereIn('email', [
            'yara@raqtan.com', 'omar@raqtan.com', 'layla@raqtan.com', 'faisal@raqtan.com',
            'bilal@raqtan.com', 'aisha@raqtan.com', 'zayd@raqtan.com', 'mona@raqtan.com',
        ])->get()->keyBy('email');
        $u = fn (string $email) => $byEmail->get($email);

        if (! $admin || ! $manager) {
            return;
        }

        $rows = [
            ['project' => 'mobile-app-redesign', 'submitter' => $u('yara@raqtan.com'), 'title' => 'Figma Pro annual plan', 'category' => 'subscriptions', 'amount' => 540.00, 'status' => 'approved', 'days' => -22, 'description' => 'Team plan for design system collaboration.'],
            ['project' => 'mobile-app-redesign', 'submitter' => $u('bilal@raqtan.com'), 'title' => 'iPhone 15 test device', 'category' => 'hardware', 'amount' => 1199.00, 'status' => 'approved', 'days' => -18],
            ['project' => 'mobile-app-redesign', 'submitter' => $employee, 'title' => 'User research participants', 'category' => 'services', 'amount' => 850.00, 'status' => 'approved', 'days' => -10],
            ['project' => 'mobile-app-redesign', 'submitter' => $employee, 'title' => 'Team offsite lunch', 'category' => 'meals', 'amount' => 280.00, 'status' => 'pending', 'days' => -3],
            ['project' => 'mobile-app-redesign', 'submitter' => $u('layla@raqtan.com'), 'title' => 'BrowserStack monthly', 'category' => 'subscriptions', 'amount' => 199.00, 'status' => 'pending', 'days' => -1],

            ['project' => 'q3-marketing-site-launch', 'submitter' => $u('mona@raqtan.com'), 'title' => 'Stock photography licenses', 'category' => 'services', 'amount' => 360.00, 'status' => 'approved', 'days' => -12],
            ['project' => 'q3-marketing-site-launch', 'submitter' => $u('aisha@raqtan.com'), 'title' => 'Copywriting freelance', 'category' => 'services', 'amount' => 1800.00, 'status' => 'approved', 'days' => -7],
            ['project' => 'q3-marketing-site-launch', 'submitter' => $u('mona@raqtan.com'), 'title' => 'Launch event venue deposit', 'category' => 'services', 'amount' => 2400.00, 'status' => 'pending', 'days' => -2],

            ['project' => 'internal-reporting-pipeline', 'submitter' => $u('omar@raqtan.com'), 'title' => 'Snowflake credits top-up', 'category' => 'software', 'amount' => 4500.00, 'status' => 'pending', 'days' => 0, 'description' => 'Initial credits for warehouse setup.'],
            ['project' => 'internal-reporting-pipeline', 'submitter' => $u('faisal@raqtan.com'), 'title' => 'Data conference ticket', 'category' => 'travel', 'amount' => 1200.00, 'status' => 'rejected', 'days' => -5, 'note' => 'Out of budget for this quarter — let\'s revisit next quarter.'],

            ['project' => 'customer-support-knowledge-base', 'submitter' => $u('aisha@raqtan.com'), 'title' => 'Help center theme license', 'category' => 'software', 'amount' => 290.00, 'status' => 'approved', 'days' => -30],
            ['project' => 'customer-support-knowledge-base', 'submitter' => $employee, 'title' => 'Screen recording software', 'category' => 'software', 'amount' => 89.00, 'status' => 'approved', 'days' => -14],

            ['project' => 'soc-2-readiness', 'submitter' => $u('zayd@raqtan.com'), 'title' => 'Compliance consultancy retainer', 'category' => 'services', 'amount' => 6500.00, 'status' => 'approved', 'days' => -45],
            ['project' => 'soc-2-readiness', 'submitter' => $manager, 'title' => 'Security training subscriptions', 'category' => 'subscriptions', 'amount' => 1200.00, 'status' => 'pending', 'days' => -4],

            ['project' => 'onboarding-refresh-v-2', 'submitter' => $u('bilal@raqtan.com'), 'title' => 'Animation library license', 'category' => 'software', 'amount' => 320.00, 'status' => 'approved', 'days' => -88],
            ['project' => 'onboarding-refresh-v-2', 'submitter' => $u('yara@raqtan.com'), 'title' => 'User testing platform', 'category' => 'services', 'amount' => 480.00, 'status' => 'approved', 'days' => -60],
        ];

        foreach ($rows as $i => $row) {
            $project = Project::where('slug', $row['project'])->first();
            $submitter = $row['submitter'];
            if (! $project || ! $submitter) {
                continue;
            }

            $approver = $manager;
            $expense = Expense::create([
                'project_id' => $project->id,
                'user_id' => $submitter->id,
                'reference' => 'EXP-'.strtoupper(Str::padLeft((string) (1000 + $i), 4, '0')),
                'title' => $row['title'],
                'description' => $row['description'] ?? null,
                'amount' => $row['amount'],
                'currency' => 'SAR',
                'category' => $row['category'],
                'expense_date' => now()->addDays($row['days'])->toDateString(),
                'status' => $row['status'],
                'approver_id' => $row['status'] === 'pending' ? null : $approver?->id,
                'decided_at' => $row['status'] === 'pending' ? null : now()->addDays($row['days'] + 1),
                'decision_note' => $row['note'] ?? null,
            ]);
        }
    }
}
