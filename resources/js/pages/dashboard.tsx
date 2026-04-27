import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, BarChart3, CheckCircle2, FolderKanban, ListChecks, Sparkles, UserPlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const ROLE_GREETING: Record<string, string> = {
    admin: 'You have full control over the workspace. Here is what is happening today.',
    manager: "Track your team's velocity and unblock work in progress.",
    employee: 'Stay on top of your assigned work and recent updates.',
};

const TEAM_PREVIEW = [
    { name: 'Sarah Mansoor', role: 'Engineering Manager', initials: 'SM', tone: 'from-violet-500 to-indigo-600' },
    { name: 'Hamza Ali', role: 'Software Engineer', initials: 'HA', tone: 'from-emerald-500 to-teal-600' },
    { name: 'Yara Saleh', role: 'Product Designer', initials: 'YS', tone: 'from-pink-500 to-fuchsia-600' },
    { name: 'Omar Khalid', role: 'Backend Engineer', initials: 'OK', tone: 'from-amber-500 to-orange-600' },
    { name: 'Layla Hassan', role: 'QA Engineer', initials: 'LH', tone: 'from-sky-500 to-cyan-600' },
];

export default function Dashboard() {
    const { user, is, can } = usePermissions();
    const getInitials = useInitials();

    if (!user) return null;

    const greeting = (() => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Good morning';
        if (hour < 18) return 'Good afternoon';
        return 'Good evening';
    })();

    const stats: Array<React.ComponentProps<typeof StatCard>> = is('admin')
        ? [
              { label: 'Workspace members', value: '11', delta: '+3 this month', trend: 'up', icon: Users, accent: 'violet' },
              { label: 'Active projects', value: '—', sub: 'Module pending', icon: FolderKanban, accent: 'blue' },
              { label: 'Open tasks', value: '—', sub: 'Module pending', icon: ListChecks, accent: 'amber' },
              { label: 'Roles configured', value: '3', sub: 'Admin · Manager · Employee', icon: BarChart3, accent: 'emerald' },
          ]
        : is('manager')
          ? [
                { label: 'My team', value: '6', delta: '2 active today', trend: 'up', icon: Users, accent: 'violet' },
                { label: 'Projects led', value: '—', sub: 'Module pending', icon: FolderKanban, accent: 'blue' },
                { label: 'Tasks in flight', value: '—', sub: 'Module pending', icon: ListChecks, accent: 'amber' },
                { label: 'On-time delivery', value: '—', sub: 'Reports module pending', icon: BarChart3, accent: 'emerald' },
            ]
          : [
                { label: 'Assigned tasks', value: '—', sub: 'Module pending', icon: ListChecks, accent: 'violet' },
                { label: 'Due this week', value: '—', sub: 'Module pending', icon: CheckCircle2, accent: 'amber' },
                { label: 'Active projects', value: '—', sub: 'Module pending', icon: FolderKanban, accent: 'blue' },
                { label: 'Completion rate', value: '—', sub: 'Reports module pending', icon: BarChart3, accent: 'emerald' },
            ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
                <PageHeader
                    eyebrow="Dashboard"
                    title={`${greeting}, ${user.name.split(' ')[0]}`}
                    description={ROLE_GREETING[user.primary_role as string] ?? ROLE_GREETING.employee}
                    actions={
                        <>
                            <RoleBadge role={user.primary_role} size="md" />
                            {can('users.create') && (
                                <Button asChild className="gap-2">
                                    <Link href={route('users.create')}>
                                        <UserPlus className="size-4" /> Invite user
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((s, i) => (
                        <StatCard key={i} {...s} />
                    ))}
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <SoftCard className="lg:col-span-2">
                        <div className="from-violet-600 via-indigo-600 to-blue-600 relative overflow-hidden bg-gradient-to-br p-7 text-white">
                            <div className="absolute -right-12 -top-12 size-56 rounded-full bg-white/15 blur-3xl" />
                            <div className="absolute -bottom-16 right-1/4 size-48 rounded-full bg-pink-400/30 blur-3xl" />
                            <div className="relative flex flex-col gap-4">
                                <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] ring-1 ring-white/25 backdrop-blur">
                                    <Sparkles className="size-3" /> Foundation ready
                                </span>
                                <div>
                                    <h3 className="font-display text-2xl font-bold leading-tight md:text-3xl">
                                        Your premium workspace is live
                                    </h3>
                                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                                        Authentication, two-factor by email, role-based access, and the user directory are wired in. Each upcoming
                                        module — Projects, Tasks, Teams, Reports — plugs into the same permission registry.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2 pt-1">
                                    {can('users.view') && (
                                        <Button asChild variant="secondary" size="sm" className="bg-white text-violet-700 hover:bg-white/90 hover:shadow-soft-md gap-2">
                                            <Link href={route('users.index')}>
                                                Manage users <ArrowUpRight className="size-3.5" />
                                            </Link>
                                        </Button>
                                    )}
                                    {can('roles.view') && (
                                        <Button asChild size="sm" className="gap-2 bg-white/15 text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25">
                                            <Link href={route('roles.index')}>
                                                Configure roles <ArrowUpRight className="size-3.5" />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                        <SoftCardBody className="grid gap-4 p-6 md:grid-cols-3">
                            {[
                                { label: 'Modules wired', value: '7', tone: 'text-violet-600' },
                                { label: 'Permissions defined', value: `${user.permissions.length}`, tone: 'text-blue-600' },
                                { label: 'System roles', value: '3', tone: 'text-emerald-600' },
                            ].map((s) => (
                                <div key={s.label} className="bg-muted/40 rounded-xl p-4">
                                    <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{s.label}</p>
                                    <p className={'font-display mt-1 text-2xl font-bold ' + s.tone}>{s.value}</p>
                                </div>
                            ))}
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Your access">Effective permissions</SoftCardTitle>
                        <SoftCardBody>
                            {user.permissions.length === 0 ? (
                                <p className="text-muted-foreground text-xs">No granular permissions assigned.</p>
                            ) : (
                                <div className="space-y-1.5">
                                    {user.permissions.slice(0, 6).map((perm) => (
                                        <div
                                            key={perm}
                                            className="bg-muted/40 ring-border/50 flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 ring-1"
                                        >
                                            <span className="font-mono text-[11px]">{perm}</span>
                                            <CheckCircle2 className="size-3.5 text-emerald-500" />
                                        </div>
                                    ))}
                                    {user.permissions.length > 6 && (
                                        <p className="text-muted-foreground pt-1 text-[11px]">+ {user.permissions.length - 6} more</p>
                                    )}
                                </div>
                            )}
                        </SoftCardBody>
                    </SoftCard>
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <SoftCard className="lg:col-span-2">
                        <SoftCardTitle
                            eyebrow="Workspace"
                            action={
                                can('users.view') && (
                                    <Button asChild size="sm" variant="soft">
                                        <Link href={route('users.index')}>
                                            View all <ArrowUpRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                )
                            }
                        >
                            Recently active members
                        </SoftCardTitle>
                        <SoftCardBody>
                            <ul className="divide-y divide-border/50">
                                {TEAM_PREVIEW.map((p) => (
                                    <li key={p.name} className="flex items-center gap-3 py-2.5">
                                        <div className={`flex size-10 items-center justify-center rounded-xl bg-gradient-to-br ${p.tone} text-sm font-bold text-white shadow-soft-sm ring-2 ring-card`}>
                                            {p.initials || getInitials(p.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold">{p.name}</p>
                                            <p className="text-muted-foreground text-xs">{p.role}</p>
                                        </div>
                                        <span className="bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset">
                                            <span className="size-1.5 rounded-full bg-emerald-500" /> Active
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Modules">Build queue</SoftCardTitle>
                        <SoftCardBody className="space-y-2.5">
                            {[
                                { name: 'Projects', status: 'next', tone: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30' },
                                { name: 'Tasks & Kanban', status: 'queued', tone: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30' },
                                { name: 'Teams', status: 'queued', tone: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30' },
                                { name: 'Reports & Analytics', status: 'queued', tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30' },
                            ].map((m) => (
                                <div key={m.name} className="bg-muted/40 ring-border/50 flex items-center justify-between rounded-xl px-3 py-2.5 ring-1">
                                    <span className="text-sm font-medium">{m.name}</span>
                                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset ${m.tone}`}>
                                        {m.status}
                                    </span>
                                </div>
                            ))}
                        </SoftCardBody>
                    </SoftCard>
                </section>
            </div>
        </AppLayout>
    );
}
