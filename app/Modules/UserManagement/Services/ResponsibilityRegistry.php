<?php

namespace App\Modules\UserManagement\Services;

/**
 * Named bundles of permissions that describe a *responsibility*, not a role.
 *
 * The organisation has two top-level roles — Super Admin and Employee — plus a
 * Manager role for workspace-wide delivery management. Everything narrower
 * ("backend & UI lead", "product manager") is a bundle granted directly to a
 * person, so a new responsibility never means a new role and the role table
 * stays readable.
 *
 * Scope is deliberately absent from these lists. A bundle says what a person
 * *may do*; which rows they may do it to comes from department, project
 * membership and team leadership, enforced by the `visibleTo` scopes and the
 * policies. Handing someone `tasks.update` does not hand them every task.
 *
 * Every bundle is filtered through PermissionRegistry::withoutSuperAdminOnly()
 * on the way out: People & Goals and administration belong to the Super Admin
 * alone, so a bundle can never hand them to anybody else. The lists below
 * still name those permissions — they document the responsibility — but the
 * filter is what is actually granted.
 */
class ResponsibilityRegistry
{
    public const DTT_MANAGER = 'dtt-manager';

    public const PRODUCT_MANAGER = 'product-manager';

    public const BACKEND_UI_LEAD = 'backend-ui-lead';

    /**
     * @return array<string, array{label: string, description: string, permissions: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            self::DTT_MANAGER => [
                'label' => 'DTT Manager',
                'description' => 'Runs delivery across the whole organisation: every project, task, team and report.',
                'permissions' => self::dttManager(),
            ],
            self::PRODUCT_MANAGER => [
                'label' => 'Product Manager',
                'description' => 'Owns the product: projects, roadmap, task assignment and delivery reporting.',
                'permissions' => self::productManager(),
            ],
            self::BACKEND_UI_LEAD => [
                'label' => 'Backend & UI Team Lead',
                'description' => 'Manages the Backend and UI teams. Scope comes from leading those teams, not from a workspace-wide grant.',
                'permissions' => self::backendUiLead(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function permissionsFor(string $key): array
    {
        return self::all()[$key]['permissions'] ?? [];
    }

    /**
     * Organisation-wide delivery management. Deliberately excludes the
     * security-critical surface — users, roles, permission schemes, workspace
     * configuration — which stays with the Super Admin (RBAC §8), along with
     * meetings, OKRs and feedback.
     *
     * @return array<int, string>
     */
    public static function dttManager(): array
    {
        return PermissionRegistry::withoutSuperAdminOnly([
            'users.view',
            'departments.view',

            'projects.view', 'projects.view-all', 'projects.create', 'projects.update',
            'projects.delete', 'projects.manage-members',

            'tasks.view', 'tasks.view-all', 'tasks.create', 'tasks.update', 'tasks.delete',
            'tasks.archive', 'tasks.assign', 'tasks.update-status', 'tasks.log-time',
            'tasks.link', 'tasks.bulk-edit', 'tasks.manage-labels',

            'workflows.view',
            // Read-only on automation: the rules themselves are workspace
            // configuration and stay with the Super Admin (RBAC §8).
            'automations.view',

            'teams.view', 'teams.manage',

            'reports.view', 'reports.view-all', 'reports.export',

            'settings.view',

            'meetings.view', 'meetings.create', 'meetings.update', 'meetings.delete', 'meetings.manage',
            'okrs.view', 'okrs.create', 'okrs.update', 'okrs.delete', 'okrs.manage',
            'feedback.view', 'feedback.give', 'feedback.manage',
        ]);
    }

    /**
     * Broad product management (RBAC §9): everything the DTT Manager can do to
     * plan and run product work, minus destructive project deletion and team
     * administration.
     *
     * @return array<int, string>
     */
    public static function productManager(): array
    {
        return PermissionRegistry::withoutSuperAdminOnly([
            'users.view',
            'departments.view',

            'projects.view', 'projects.view-all', 'projects.create', 'projects.update',
            'projects.manage-members',

            'tasks.view', 'tasks.view-all', 'tasks.create', 'tasks.update', 'tasks.archive',
            'tasks.assign', 'tasks.update-status', 'tasks.log-time', 'tasks.link',
            'tasks.bulk-edit', 'tasks.manage-labels',

            'workflows.view',
            'automations.view',

            'teams.view',

            'reports.view', 'reports.view-all', 'reports.export',

            'meetings.view', 'meetings.create', 'meetings.update', 'meetings.delete',
            'okrs.view', 'okrs.create', 'okrs.update', 'okrs.manage',
            'feedback.view', 'feedback.give',
        ]);
    }

    /**
     * Backend & UI lead (RBAC §10). No `*.view-all` here on purpose: the lead's
     * reach is the Backend and UI teams they lead, which the visibility scopes
     * derive from team leadership. HR, IT, QA and Mobile stay invisible unless
     * he is a member of the project in question.
     *
     * @return array<int, string>
     */
    public static function backendUiLead(): array
    {
        return PermissionRegistry::withoutSuperAdminOnly([
            'users.view',

            'projects.view',

            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'tasks.update-status', 'tasks.log-time', 'tasks.link', 'tasks.bulk-edit',
            'tasks.manage-labels',

            'workflows.view',

            'teams.view',

            'reports.view',

            'meetings.view', 'meetings.create', 'meetings.update',
            'okrs.view', 'okrs.create', 'okrs.update',
            'feedback.view', 'feedback.give',
        ]);
    }
}
