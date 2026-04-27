import { type AuthUser, type NavItem } from '@/types';
import { BarChart3, FolderKanban, LayoutGrid, ListChecks, Settings, ShieldCheck, Users, UsersRound } from 'lucide-react';

export interface NavSection {
    label: string;
    items: NavItem[];
}

export function buildSidebarSections(user: AuthUser | null): NavSection[] {
    if (!user) return [];

    const can = (perm?: string) => !perm || user.is_admin || user.permissions.includes(perm);

    const workspace: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Projects', url: '/projects', icon: FolderKanban, permission: 'projects.view' },
        { title: 'Tasks', url: '/tasks', icon: ListChecks, permission: 'tasks.view' },
        { title: 'Teams', url: '/teams', icon: UsersRound, permission: 'teams.view' },
    ].filter((item) => can(item.permission));

    const insights: NavItem[] = [
        { title: 'Reports', url: '/reports', icon: BarChart3, permission: 'reports.view' },
    ].filter((item) => can(item.permission));

    const administration: NavItem[] = [
        { title: 'Users', url: '/users', icon: Users, permission: 'users.view' },
        { title: 'Roles & Permissions', url: '/roles', icon: ShieldCheck, permission: 'roles.view' },
        { title: 'Workspace Settings', url: '/settings/profile', icon: Settings },
    ].filter((item) => can(item.permission));

    const sections: NavSection[] = [{ label: 'Workspace', items: workspace }];

    if (insights.length > 0) {
        sections.push({ label: 'Insights', items: insights });
    }

    if (administration.length > 0) {
        sections.push({ label: 'Administration', items: administration });
    }

    return sections;
}
