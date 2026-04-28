import { type AuthUser, type NavItem } from '@/types';
import { Activity, BarChart3, CalendarClock, FolderKanban, LayoutGrid, ListChecks, MessageSquareText, Receipt, Settings, ShieldCheck, Target, Users, UsersRound, Wallet } from 'lucide-react';

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

    const collaboration: NavItem[] = [
        { title: 'Meetings', url: '/meetings', icon: CalendarClock, permission: 'meetings.view' },
        { title: 'OKRs', url: '/okrs', icon: Target, permission: 'okrs.view' },
        { title: 'Feedback', url: '/feedback', icon: MessageSquareText, permission: 'feedback.view' },
    ].filter((item) => can(item.permission));

    const finance: NavItem[] = [
        { title: 'Expenses', url: '/expenses', icon: Wallet, permission: 'expenses.view' },
        { title: 'Approvals', url: '/expenses/approvals', icon: Receipt, permission: 'expenses.approve' },
    ].filter((item) => can(item.permission));

    const insights: NavItem[] = [
        { title: 'Reports', url: '/reports', icon: BarChart3, permission: 'reports.view' },
    ].filter((item) => can(item.permission));

    const administration: NavItem[] = [
        { title: 'Users', url: '/users', icon: Users, permission: 'users.view' },
        { title: 'Roles & Permissions', url: '/roles', icon: ShieldCheck, permission: 'roles.view' },
        { title: 'Activity log', url: '/activity', icon: Activity, permission: 'users.view' },
        { title: 'Workspace Settings', url: '/settings/profile', icon: Settings },
    ].filter((item) => can(item.permission));

    const sections: NavSection[] = [{ label: 'Workspace', items: workspace }];

    if (collaboration.length > 0) sections.push({ label: 'People & Goals', items: collaboration });
    if (finance.length > 0) sections.push({ label: 'Finance', items: finance });
    if (insights.length > 0) sections.push({ label: 'Insights', items: insights });
    if (administration.length > 0) sections.push({ label: 'Administration', items: administration });

    return sections;
}
