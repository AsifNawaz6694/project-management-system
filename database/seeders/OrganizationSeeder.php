<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Department;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\ResponsibilityRegistry as Responsibility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The organisation itself: one Super Admin plus the DTT / HR / IT roster.
 *
 * Everyone is an Employee. Two people additionally hold the Manager role, and
 * responsibilities narrower than that are direct permission grants keyed off
 * ResponsibilityRegistry — no role is invented per job title.
 *
 * Idempotent by email:
 *   - profile, department, role, teams and grants are re-synced on every run;
 *   - passwords are set on creation only, so re-seeding never resets anyone;
 *   - accounts on the superseded demo domains are removed once.
 *
 * Depends on RolePermissionSeeder, DepartmentSeeder and TeamSeeder.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $this->purgeLegacyAccounts();

        $roles = Role::query()->pluck('id', 'slug');
        $departments = Department::query()->pluck('id', 'slug');
        $teams = Team::query()->pluck('id', 'slug');

        foreach ($this->roster() as $person) {
            $user = $this->upsertUser($person, $departments);

            $user->roles()->sync([$roles[$person['role']]]);

            $user->syncDirectPermissionsBySlug(
                $person['responsibility'] ? Responsibility::permissionsFor($person['responsibility']) : [],
            );

            $this->syncTeams($user, $person, $teams);
        }

        $this->syncTeamLeads($teams);
    }

    /**
     * The Super Admin, then DTT, HR and IT.
     *
     * `role`           — workspace role; the list stays short on purpose.
     * `responsibility` — extra permissions granted to the person directly.
     * `teams` / `lead` — delivery team membership, and the teams they run.
     *
     * @return array<int, array<string, mixed>>
     */
    private function roster(): array
    {
        $superAdmin = config('rbac.super_admin');

        return [
            // Super Admin. Asif is both the unrestricted account and DTT's
            // Product Manager; the role already grants everything, but the
            // responsibility is recorded so the org chart stays truthful if the
            // role is ever downgraded.
            [
                'name' => $superAdmin['name'],
                'email' => $superAdmin['email'],
                'job_title' => 'Product Manager',
                'department' => Department::DTT,
                'role' => Role::ADMIN,
                'responsibility' => Responsibility::PRODUCT_MANAGER,
                'teams' => [],
                'lead' => [],
            ],

            // DTT
            [
                'name' => 'Saad Khan',
                'email' => 'saad@bargoventures.com',
                'job_title' => 'DTT Manager',
                'department' => Department::DTT,
                'role' => Role::MANAGER,
                'responsibility' => Responsibility::DTT_MANAGER,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Marcin',
                'email' => 'marcin@bargoventures.com',
                'job_title' => 'DTT Manager',
                'department' => Department::DTT,
                'role' => Role::MANAGER,
                'responsibility' => Responsibility::DTT_MANAGER,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Mohammad Adnan',
                'email' => 'adnan@bargoventures.com',
                'job_title' => 'Backend & UI Team Lead',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => Responsibility::BACKEND_UI_LEAD,
                'teams' => [TeamSeeder::BACKEND, TeamSeeder::UI],
                'lead' => [TeamSeeder::BACKEND, TeamSeeder::UI],
            ],
            [
                'name' => 'Yousuf Munir',
                'email' => 'yousuf@bargoventures.com',
                'job_title' => 'Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Mohammad Ubaid',
                'email' => 'ubaid@bargoventures.com',
                'job_title' => 'Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Mohammad Sameer',
                'email' => 'sameer@bargoventures.com',
                'job_title' => 'UI Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::UI],
                'lead' => [],
            ],
            [
                'name' => 'Muneeb',
                'email' => 'muneeb@bargoventures.com',
                'job_title' => 'UI Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::UI],
                'lead' => [],
            ],
            [
                'name' => 'Adil',
                'email' => 'adil@bargoventures.com',
                'job_title' => 'Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Hania Khan',
                'email' => 'hania@bargoventures.com',
                'job_title' => 'Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Talha Kayani',
                'email' => 'talha.kayani@bargoventures.com',
                'job_title' => 'Backend Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::BACKEND],
                'lead' => [],
            ],
            [
                'name' => 'Mohammad Usman',
                'email' => 'usman@bargoventures.com',
                'job_title' => 'Backend Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::BACKEND],
                'lead' => [],
            ],
            [
                'name' => 'Mohammed Raheel',
                'email' => 'raheel@bargoventures.com',
                'job_title' => 'Backend Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::BACKEND],
                'lead' => [],
            ],
            [
                'name' => 'Ashar Khan',
                'email' => 'ashar@bargoventures.com',
                'job_title' => 'QA Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::QA],
                'lead' => [],
            ],
            [
                'name' => 'Taha',
                'email' => 'taha@bargoventures.com',
                'job_title' => 'QA Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::QA],
                'lead' => [],
            ],
            [
                'name' => 'Ahmed Siddiqui',
                'email' => 'ahmed@bargoventures.com',
                'job_title' => 'Mobile Team Member',
                'department' => Department::DTT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [TeamSeeder::MOBILE],
                'lead' => [],
            ],

            // HR
            [
                'name' => 'Farhan',
                'email' => 'farhan@bargoventures.com',
                'job_title' => 'HR',
                'department' => Department::HR,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],

            // IT
            [
                'name' => 'Waqas Imam',
                'email' => 'waqas@bargoventures.com',
                'job_title' => 'IT',
                'department' => Department::IT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
            [
                'name' => 'Shelbayah',
                'email' => 'shelbayah@bargoventures.com',
                'job_title' => 'IT',
                'department' => Department::IT,
                'role' => Role::EMPLOYEE,
                'responsibility' => null,
                'teams' => [],
                'lead' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  Collection<string, int>  $departments
     */
    private function upsertUser(array $person, Collection $departments): User
    {
        $user = User::query()->firstOrNew(['email' => $person['email']]);

        // Only ever set on creation — re-seeding must not reset a live password.
        if (! $user->exists) {
            $user->password = Hash::make($this->passwordFor($person['email']));
            $user->email_verified_at = now();
        }

        $user->fill([
            'name' => $person['name'],
            'job_title' => $person['job_title'],
            'department_id' => $departments[$person['department']],
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $user->save();

        return $user;
    }

    private function passwordFor(string $email): string
    {
        return $email === config('rbac.super_admin.email')
            ? config('rbac.super_admin.password')
            : config('rbac.default_password');
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  Collection<string, int>  $teams
     */
    private function syncTeams(User $user, array $person, Collection $teams): void
    {
        $sync = [];

        foreach ($person['teams'] as $slug) {
            $sync[$teams[$slug]] = ['role' => in_array($slug, $person['lead'], true) ? 'lead' : 'member'];
        }

        $user->teams()->sync($sync);
    }

    /**
     * Mirror the pivot's `lead` rows onto `teams.lead_id`, so the team page and
     * the visibility scopes agree about who runs a team.
     *
     * @param  Collection<string, int>  $teams
     */
    private function syncTeamLeads(Collection $teams): void
    {
        foreach ($this->roster() as $person) {
            if ($person['lead'] === []) {
                continue;
            }

            $leadId = User::query()->where('email', $person['email'])->value('id');

            foreach ($person['lead'] as $slug) {
                Team::query()->whereKey($teams[$slug])->update(['lead_id' => $leadId]);
            }
        }
    }

    /**
     * Drop the superseded demo accounts and everything hanging off them.
     *
     * Projects and tasks null out their user references (the migrations use
     * `nullOnDelete`), so this cannot orphan work. Accounts an admin created
     * through the UI are untouched — only the configured legacy domains go.
     */
    private function purgeLegacyAccounts(): void
    {
        $domains = array_filter(array_map('trim', (array) config('rbac.purge_domains', [])));

        if ($domains === []) {
            return;
        }

        $ids = User::query()
            ->where(function ($q) use ($domains) {
                foreach ($domains as $domain) {
                    $q->orWhere('email', 'like', '%@'.$domain);
                }
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_user')->whereIn('user_id', $ids)->delete();
        DB::table('permission_user')->whereIn('user_id', $ids)->delete();
        DB::table('team_user')->whereIn('user_id', $ids)->delete();
        DB::table('project_user')->whereIn('user_id', $ids)->delete();

        User::query()->whereIn('id', $ids)->delete();

        $this->command?->info("Removed {$ids->count()} superseded account(s).");
    }
}
