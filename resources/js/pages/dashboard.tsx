import { AreaChart } from '@/components/charts/area-chart';
import { DonutChart } from '@/components/charts/donut-chart';
import { LiveClock } from '@/components/live-clock';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, COLOR_GRADIENT, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, isOverdue, relativeDue, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    FolderKanban,
    ListChecks,
    Radio,
    Sparkles,
    TrendingUp,
    UserPlus,
    Zap,
    type LucideIcon,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const ICONS: Record<string, LucideIcon> = {
    'list-checks': ListChecks,
    'folder-kanban': FolderKanban,
    'calendar-clock': CalendarClock,
    'check-circle-2': CheckCircle2,
    'alert-circle': AlertCircle,
};

interface KPI {
    label: string;
    value: string | number;
    sub?: string;
    icon?: string;
    accent?: string;
}

interface ProjectMini {
    id: number;
    slug: string;
    title: string;
    color: ProjectColor;
    progress: number;
    status: string;
}

interface TaskMini {
    id: number;
    title: string;
    due_date: string | null;
    completed_at?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    project: { id: number; slug: string; title: string; color: ProjectColor } | null;
    assignee?: { id: number; name: string; initials: string } | null;
}

interface ActivityItem {
    id: number;
    description: string | null;
    action: string;
    module: string | null;
    created_at: string;
}

interface DashboardProps {
    role: string | null;
    kpis: KPI[];
    completion?: { total_tasks: number; completed_tasks: number; completion_rate: number };
    statusBreakdown?: Array<{ key: string; label: string; value: number; tone: string }>;
    burndown?: Array<{ label: string; created: number; completed: number }>;
    velocity?: Array<{ label: string; created: number; completed: number }>;
    topProjects?: ProjectMini[];
    topPerformers?: Array<{ id: number; name: string; initials: string; job_title?: string | null; completed_tasks: number }>;
    upcomingDeadlines?: TaskMini[];
    myProjects?: ProjectMini[];
    recentActivity?: ActivityItem[];
}

export default function Dashboard(props: DashboardProps) {
    const { user, is, can } = usePermissions();
    const getInitials = useInitials();

    if (!user) return null;

    const isLeadership = is('admin', 'manager');

    const greeting = (() => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Good morning';
        if (hour < 18) return 'Good afternoon';
        return 'Good evening';
    })();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex w-full flex-1 flex-col gap-7 p-4 md:p-6">
                {/* Header */}
                <section className="animate-fade-down bg-card ring-border/70 shadow-soft-sm relative overflow-hidden rounded-2xl p-6 ring-1 md:p-8">
                    {/* Soft brand wash */}
                    <div aria-hidden className="bg-aurora absolute inset-0" />
                    <div aria-hidden className="via-primary/40 absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent to-transparent" />

                    <div className="relative grid gap-6 md:grid-cols-[1fr_auto] md:items-start">
                        <div className="space-y-3">
                            <div className="animate-fade-right flex items-center gap-2">
                                <span className="relative inline-flex size-2 shrink-0 rounded-full bg-emerald-500">
                                    <span className="animate-glow-pulse absolute -inset-1 rounded-full bg-emerald-500/25" />
                                </span>
                                <span className="text-muted-foreground text-[11px] font-semibold tracking-[0.18em] uppercase">
                                    {isLeadership ? 'Workspace overview' : 'Your workspace'}
                                </span>
                            </div>

                            <h1 className="font-display animate-fade-right text-3xl leading-[1.1] font-bold tracking-tight delay-100 md:text-4xl">
                                {greeting}, <span className="text-primary">{user.name.split(' ')[0]}</span>
                            </h1>

                            <p className="text-muted-foreground animate-fade-right max-w-2xl text-sm delay-200">
                                {isLeadership
                                    ? "Here's what's moving across the workspace — projects, tasks, spend, and your team's pulse, right now."
                                    : "Your work in flight. Deadlines, momentum, and what's on your plate today."}
                            </p>

                            <div className="animate-fade-right flex flex-wrap items-center gap-2 pt-2 delay-300">
                                <RoleBadge role={user.primary_role} size="md" />
                                {can('users.create') && (
                                    <Button asChild size="sm" className="gap-2">
                                        <Link href={route('users.create')}>
                                            <UserPlus className="size-4" /> Invite user
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild size="sm" variant="soft" className="gap-2">
                                    <Link href={route('tasks.index')}>
                                        <Zap className="size-4" /> Jump to tasks
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div className="animate-fade-left flex flex-col items-start gap-3 delay-200 md:items-end">
                            <div className="bg-muted/50 ring-border/60 rounded-xl px-4 py-2.5 ring-1">
                                <LiveClock />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <HeroPill icon={Activity} label="Active" />
                                <HeroPill icon={TrendingUp} label="On track" />
                                <HeroPill icon={Radio} label="Realtime" />
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {props.kpis.map((kpi, i) => {
                        const trend = props.burndown ?? props.velocity ?? [];
                        const series = trend.length ? (i % 2 === 0 ? trend.map((p) => p.completed) : trend.map((p) => p.created)) : undefined;
                        return (
                            <div key={i} style={{ animationDelay: `${i * 90}ms` }} className="animate-fade-up">
                                <StatCard
                                    label={kpi.label}
                                    value={kpi.value}
                                    sub={kpi.sub}
                                    icon={ICONS[kpi.icon ?? '']}
                                    accent={kpi.accent as 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'slate' | 'sky' | 'pink'}
                                    sparkline={series}
                                />
                            </div>
                        );
                    })}
                </section>

                {isLeadership ? <LeadershipBody {...props} /> : <EmployeeBody {...props} />}

                <section className="grid gap-5 lg:grid-cols-3">
                    <SoftCard className="animate-fade-up delay-300 lg:col-span-2">
                        <SoftCardTitle eyebrow="Pipeline">Upcoming deadlines</SoftCardTitle>
                        <SoftCardBody>
                            {(props.upcomingDeadlines ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-xs">Nothing due soon.</p>
                            ) : (
                                <ul className="divide-border/60 divide-y">
                                    {props.upcomingDeadlines?.map((t, idx) => {
                                        const priority = TASK_PRIORITY_META[t.priority];
                                        // Aggregates span projects, so fall back to shared status metadata.
                                        const overdue = isOverdue(t.due_date, t.completed_at);
                                        return (
                                            <li key={t.id} className="animate-fade-right" style={{ animationDelay: `${idx * 60}ms` }}>
                                                <Link
                                                    href={route('tasks.show', t.id)}
                                                    className="group hover:bg-muted/40 flex items-center gap-3 rounded-xl px-2 py-3 transition-colors"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <p className="group-hover:text-primary truncate text-sm font-semibold">{t.title}</p>
                                                        <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-[11px]">
                                                            {t.project && (
                                                                <>
                                                                    <span
                                                                        className={cn(
                                                                            'size-1.5 rounded-full',
                                                                            COLOR_DOT[t.project.color] ?? 'bg-indigo-600',
                                                                        )}
                                                                    />
                                                                    <span className="truncate">{t.project.title}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                                                            priority?.chip,
                                                        )}
                                                    >
                                                        {priority?.label}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 text-[11px] font-semibold',
                                                            overdue && 'text-red-600 dark:text-red-400',
                                                        )}
                                                    >
                                                        <CalendarClock className="size-3.5" /> {relativeDue(t.due_date)}
                                                    </span>
                                                    {t.assignee && (
                                                        <span className="bg-muted text-muted-foreground ring-card hidden size-6 items-center justify-center rounded-full text-[9px] font-bold ring-2 sm:inline-flex">
                                                            {t.assignee.initials || getInitials(t.assignee.name)}
                                                        </span>
                                                    )}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard className="animate-fade-up delay-400">
                        <SoftCardTitle eyebrow="Activity">Recent updates</SoftCardTitle>
                        <SoftCardBody>
                            <ol className="before:bg-border relative space-y-3 pl-6 before:absolute before:top-1.5 before:bottom-1.5 before:left-2 before:w-px">
                                {(props.recentActivity ?? []).length === 0 && <p className="text-muted-foreground text-xs">No recent activity.</p>}
                                {props.recentActivity?.map((a, i) => (
                                    <li key={a.id} className="animate-fade-left relative" style={{ animationDelay: `${i * 70}ms` }}>
                                        <span
                                            className={cn(
                                                'ring-card absolute top-1 -left-6 size-2.5 rounded-full ring-2',
                                                i === 0 ? 'bg-primary' : 'bg-border',
                                            )}
                                        />
                                        <p className="text-xs leading-snug">{a.description ?? a.action}</p>
                                        <p className="text-muted-foreground mt-0.5 text-[10px]">{relativeTime(a.created_at)}</p>
                                    </li>
                                ))}
                            </ol>
                        </SoftCardBody>
                    </SoftCard>
                </section>
            </div>
        </AppLayout>
    );
}

function LeadershipBody(props: DashboardProps) {
    const completion = props.completion ?? { total_tasks: 0, completed_tasks: 0, completion_rate: 0 };

    return (
        <>
            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="animate-fade-up delay-150 lg:col-span-2">
                    <SoftCardTitle eyebrow="Velocity">Tasks created vs. completed (last 14 days)</SoftCardTitle>
                    <SoftCardBody>
                        <AreaChart
                            points={(props.burndown ?? []).map((p) => ({ label: p.label, created: p.created, completed: p.completed }))}
                            series={[
                                { key: 'created', label: 'Created', tone: 'blue' },
                                { key: 'completed', label: 'Completed', tone: 'emerald' },
                            ]}
                            height={220}
                        />
                        <div className="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2 rounded-full bg-blue-600" /> Created
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2 rounded-full bg-emerald-600" /> Completed
                            </span>
                        </div>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard className="animate-fade-up delay-200">
                    <SoftCardTitle eyebrow="Portfolio">Project status</SoftCardTitle>
                    <SoftCardBody>
                        <DonutChart
                            slices={(props.statusBreakdown ?? []).map((s) => ({
                                key: s.key,
                                label: s.label,
                                value: s.value,
                                tone: s.tone.includes('emerald')
                                    ? 'emerald'
                                    : s.tone.includes('blue')
                                      ? 'blue'
                                      : s.tone.includes('amber')
                                        ? 'amber'
                                        : s.tone.includes('violet')
                                          ? 'violet'
                                          : 'rose',
                            }))}
                            centerValue={`${completion.completion_rate}%`}
                            centerLabel="Tasks done"
                        />
                    </SoftCardBody>
                </SoftCard>
            </section>

            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="animate-fade-up delay-200 lg:col-span-2">
                    <SoftCardTitle
                        eyebrow="Projects"
                        action={
                            <Button asChild size="sm" variant="soft">
                                <Link href={route('projects.index')}>
                                    All <ArrowUpRight className="size-3.5" />
                                </Link>
                            </Button>
                        }
                    >
                        Top projects by progress
                    </SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-3">
                            {(props.topProjects ?? []).map((p, idx) => (
                                <li key={p.id} className="animate-fade-right" style={{ animationDelay: `${300 + idx * 80}ms` }}>
                                    <Link
                                        href={route('projects.show', p.slug)}
                                        className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group block rounded-xl p-3 ring-1"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                                <div
                                                    className={cn(
                                                        'shadow-soft-xs flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white',
                                                        COLOR_GRADIENT[p.color],
                                                    )}
                                                >
                                                    {p.title.slice(0, 2).toUpperCase()}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="group-hover:text-primary truncate text-sm font-semibold">{p.title}</p>
                                                    <p className="text-muted-foreground text-[11px] capitalize">{p.status.replace('_', ' ')}</p>
                                                </div>
                                            </div>
                                            <span className="font-display text-sm font-bold tabular-nums">{p.progress}%</span>
                                        </div>
                                        <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                            <div
                                                className={cn('animate-bar-grow h-full rounded-full bg-gradient-to-r', COLOR_GRADIENT[p.color])}
                                                style={{ width: `${Math.max(2, p.progress)}%`, animationDelay: '500ms' }}
                                            />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                            {(props.topProjects ?? []).length === 0 && <p className="text-muted-foreground text-xs">No projects yet.</p>}
                        </ul>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard className="animate-fade-up delay-300">
                    <SoftCardTitle eyebrow="Team">Top performers</SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-2.5">
                            {(props.topPerformers ?? []).map((p, i) => (
                                <li
                                    key={p.id}
                                    className="bg-muted/30 ring-border/60 hover-lift animate-fade-right flex items-center gap-3 rounded-xl p-3 ring-1"
                                    style={{ animationDelay: `${400 + i * 80}ms` }}
                                >
                                    <div
                                        className={cn(
                                            'flex size-9 items-center justify-center rounded-xl text-xs font-bold',
                                            i === 0
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {p.initials}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">{p.name}</p>
                                        <p className="text-muted-foreground truncate text-[11px]">{p.job_title ?? '—'}</p>
                                    </div>
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 tabular-nums ring-1 ring-emerald-200/70 ring-inset dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30">
                                        <CheckCircle2 className="size-3" /> {p.completed_tasks}
                                    </span>
                                </li>
                            ))}
                            {(props.topPerformers ?? []).length === 0 && <p className="text-muted-foreground text-xs">No completion activity yet.</p>}
                        </ul>
                    </SoftCardBody>
                </SoftCard>
            </section>
        </>
    );
}

function EmployeeBody(props: DashboardProps) {
    const completion = props.completion ?? { total_tasks: 0, completed_tasks: 0, completion_rate: 0 };

    return (
        <>
            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="animate-fade-up delay-150 lg:col-span-2">
                    <SoftCardTitle eyebrow="My velocity">Completed tasks (last 7 days)</SoftCardTitle>
                    <SoftCardBody>
                        <AreaChart
                            points={(props.velocity ?? []).map((p) => ({ label: p.label, completed: p.completed }))}
                            series={[{ key: 'completed', label: 'Completed', tone: 'emerald' }]}
                            height={200}
                        />
                    </SoftCardBody>
                </SoftCard>

                <SoftCard className="animate-fade-up delay-200">
                    <SoftCardTitle eyebrow="Personal progress">Completion rate</SoftCardTitle>
                    <SoftCardBody>
                        <DonutChart
                            slices={[
                                { key: 'completed', label: 'Completed', value: completion.completed_tasks, tone: 'emerald' },
                                {
                                    key: 'remaining',
                                    label: 'Remaining',
                                    value: Math.max(0, completion.total_tasks - completion.completed_tasks),
                                    tone: 'slate',
                                },
                            ]}
                            centerValue={`${completion.completion_rate}%`}
                            centerLabel="Done"
                        />
                    </SoftCardBody>
                </SoftCard>
            </section>

            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="animate-fade-up delay-200 lg:col-span-2">
                    <SoftCardTitle
                        eyebrow="My projects"
                        action={
                            <Button asChild size="sm" variant="soft">
                                <Link href={route('projects.index')}>
                                    All <ArrowUpRight className="size-3.5" />
                                </Link>
                            </Button>
                        }
                    >
                        Where you're contributing
                    </SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-3">
                            {(props.myProjects ?? []).map((p, idx) => (
                                <li key={p.id} className="animate-fade-right" style={{ animationDelay: `${300 + idx * 80}ms` }}>
                                    <Link
                                        href={route('projects.show', p.slug)}
                                        className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group block rounded-xl p-3 ring-1"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                                <div
                                                    className={cn(
                                                        'shadow-soft-xs flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white',
                                                        COLOR_GRADIENT[p.color],
                                                    )}
                                                >
                                                    {p.title.slice(0, 2).toUpperCase()}
                                                </div>
                                                <p className="group-hover:text-primary truncate text-sm font-semibold">{p.title}</p>
                                            </div>
                                            <span className="font-display text-sm font-bold tabular-nums">{p.progress}%</span>
                                        </div>
                                        <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                            <div
                                                className={cn('animate-bar-grow h-full rounded-full bg-gradient-to-r', COLOR_GRADIENT[p.color])}
                                                style={{ width: `${Math.max(2, p.progress)}%`, animationDelay: '500ms' }}
                                            />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                            {(props.myProjects ?? []).length === 0 && (
                                <p className="text-muted-foreground text-xs">You haven't been added to any projects yet.</p>
                            )}
                        </ul>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard className="animate-fade-up delay-300">
                    <SoftCardTitle eyebrow="Quick actions">Shortcuts</SoftCardTitle>
                    <SoftCardBody className="space-y-2">
                        <QuickLink
                            href={route('tasks.index', { assignee: 'me' })}
                            icon={ListChecks}
                            label="My tasks"
                            tone="bg-indigo-50 text-indigo-700 dark:bg-indigo-500/12 dark:text-indigo-300"
                        />
                        <QuickLink
                            href={route('projects.index')}
                            icon={FolderKanban}
                            label="Browse projects"
                            tone="bg-blue-50 text-blue-700 dark:bg-blue-500/12 dark:text-blue-300"
                        />
                        <QuickLink
                            href={route('notifications.index')}
                            icon={Sparkles}
                            label="Notifications"
                            tone="bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300"
                        />
                    </SoftCardBody>
                </SoftCard>
            </section>
        </>
    );
}

function HeroPill({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
    return (
        <span className="bg-muted/50 ring-border/60 text-muted-foreground inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-[0.12em] uppercase ring-1">
            <Icon className="text-primary size-3" />
            {label}
        </span>
    );
}

function QuickLink({ href, icon: Icon, label, tone }: { href: string; icon: LucideIcon; label: string; tone: string }) {
    return (
        <Link
            href={href}
            className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group flex items-center gap-3 rounded-xl p-3 ring-1"
        >
            <div className={cn('flex size-9 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105', tone)}>
                <Icon className="size-4" />
            </div>
            <span className="text-sm font-semibold">{label}</span>
            <ArrowUpRight className="text-muted-foreground ml-auto size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
        </Link>
    );
}

function relativeTime(iso: string): string {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(iso).toLocaleDateString();
}
