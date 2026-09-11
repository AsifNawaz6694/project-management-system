import { type AuthUser, type NavItem } from '@/types';
import {
    Activity,
    BarChart3,
    CalendarClock,
    FolderKanban,
    GitBranch,
    KeyRound,
    LayoutGrid,
    ListChecks,
    MessageSquareText,
    Settings,
    ShieldCheck,
    Target,
    Users,
    UsersRound,
    Zap,
} from 'lucide-react';

export interface NavSection {
    label: string;
    items: NavItem[];
}

export function buildSidebarSections(user: AuthUser | null): NavSection[] {
    if (!user) return [];

    const can = (perm?: string) => !perm || user.is_admin || user.permissions.includes(perm);

    // People & Goals and Administration are Super Admin surfaces. The gate is
    // the role, not the permissions, so a direct grant can never surface them
    // for anyone else; the seeder keeps the matching permissions off the
    // Manager and Employee roles so the routes refuse them too.
    const isSuperAdmin = user.is_admin;

    const workspace: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Projects', url: '/projects', icon: FolderKanban, permission: 'projects.view' },
        { title: 'Tasks', url: '/tasks', icon: ListChecks, permission: 'tasks.view' },
        { title: 'Teams', url: '/teams', icon: UsersRound, permission: 'teams.view' },
    ].filter((item) => can(item.permission));

    const collaboration: NavItem[] = !isSuperAdmin
        ? []
        : [
              { title: 'Meetings', url: '/meetings', icon: CalendarClock, permission: 'meetings.view' },
              { title: 'OKRs', url: '/okrs', icon: Target, permission: 'okrs.view' },
              { title: 'Feedback', url: '/feedback', icon: MessageSquareText, permission: 'feedback.view' },
          ].filter((item) => can(item.permission));

    const insights: NavItem[] = [{ title: 'Reports', url: '/reports', icon: BarChart3, permission: 'reports.view' }].filter((item) =>
        can(item.permission),
    );

    const administration: NavItem[] = !isSuperAdmin
        ? []
        : [
              { title: 'Users', url: '/users', icon: Users, permission: 'users.view' },
              { title: 'Roles & Permissions', url: '/roles', icon: ShieldCheck, permission: 'roles.view' },
              { title: 'Workflows', url: '/workflows', icon: GitBranch, permission: 'workflows.view' },
              { title: 'Permission schemes', url: '/permission-schemes', icon: KeyRound, permission: 'permission-schemes.view' },
              { title: 'Automation', url: '/automations', icon: Zap, permission: 'automations.view' },
              { title: 'Activity log', url: '/activity', icon: Activity, permission: 'users.view' },
              { title: 'Workspace Settings', url: '/settings/profile', icon: Settings },
          ].filter((item) => can(item.permission));

    const sections: NavSection[] = [{ label: 'Workspace', items: workspace }];

    if (collaboration.length > 0) sections.push({ label: 'People & Goals', items: collaboration });
    if (insights.length > 0) sections.push({ label: 'Insights', items: insights });
    if (administration.length > 0) sections.push({ label: 'Administration', items: administration });

    return sections;
}
