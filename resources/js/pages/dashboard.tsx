import { AreaChart } from '@/components/charts/area-chart';
import { DonutChart } from '@/components/charts/donut-chart';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, COLOR_GRADIENT, type ProjectColor } from '@/lib/projects';
import { cn } from '@/lib/utils';
import { TASK_PRIORITY_META, TASK_STATUS_META, isOverdue, relativeDue, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { LiveClock } from '@/components/live-clock';
import { ParticleField } from '@/components/particle-field';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    FolderKanban,
    Hourglass,
    ListChecks,
    Radio,
    Sparkles,
    TrendingUp,
    UserPlus,
    Wallet,
    Zap,
    type LucideIcon,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const ICONS: Record<string, LucideIcon> = {
    'list-checks': ListChecks,
    'folder-kanban': FolderKanban,
    'wallet': Wallet,
    'hourglass': Hourglass,
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
    budget?: number;
    spent?: number;
    utilization?: number;
}

interface TaskMini {
    id: number;
    title: string;
    due_date: string | null;
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
    budget?: { total: number; spent: number; utilization: number };
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
                {/* Cinematic hero command deck */}
                <section className="animate-fade-down relative overflow-hidden rounded-3xl p-6 text-white shadow-soft-xl md:p-10">
                    {/* Animated aurora layer */}
                    <div aria-hidden className="bg-aurora animate-aurora absolute inset-0" style={{ backgroundColor: '#0f0a2e' }} />
                    <div aria-hidden className="absolute inset-0 bg-gradient-to-br from-violet-700/80 via-indigo-700/70 to-blue-900/80" />
                    {/* Floating blobs */}
                    <div aria-hidden className="absolute -left-20 -top-24 size-80 animate-blob rounded-full bg-fuchsia-400/30 blur-3xl" />
                    <div aria-hidden className="absolute -right-16 bottom-0 size-96 animate-blob rounded-full bg-cyan-400/25 blur-3xl" style={{ animationDelay: '-5s' }} />
                    <div aria-hidden className="absolute right-1/4 top-0 size-48 animate-float-slow rounded-full bg-emerald-400/20 blur-2xl" />
                    {/* Dot grid */}
                    <div aria-hidden className="absolute inset-0 opacity-[0.08]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '28px 28px' }} />
                    {/* Particles */}
                    <ParticleField count={28} className="pointer-events-none absolute inset-0" />
                    {/* Ribbon shine sweep */}
                    <div aria-hidden className="pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-white/10 to-transparent animate-ribbon" />

                    <div className="relative grid gap-6 md:grid-cols-[1fr_auto] md:items-start">
                        <div className="space-y-3">
                            <div className="flex items-center gap-2 animate-fade-right">
                                <span className="relative inline-flex size-2.5 shrink-0 rounded-full bg-emerald-400">
                                    <span className="absolute inset-0 rounded-full bg-emerald-400 animate-live-blink" />
                                    <span className="absolute -inset-1 rounded-full bg-emerald-400/40 animate-glow-pulse" />
                                </span>
                                <span className="text-[11px] font-bold uppercase tracking-[0.22em] text-white/80">
                                    Live · {isLeadership ? 'Workspace overview' : 'Personal cockpit'}
                                </span>
                            </div>

                            <h1 className="font-display text-4xl font-bold leading-[1.05] tracking-tight md:text-5xl animate-fade-right delay-100">
                                <span className="inline-block">{greeting},</span>{' '}
                                <span className="text-shine inline-block bg-gradient-to-r from-white via-amber-100 to-emerald-200 bg-clip-text text-transparent">
                                    {user.name.split(' ')[0]}
                                </span>
                                <span className="ml-3 inline-flex animate-float-fast">
                                    <Sparkles className="size-9 text-amber-200 drop-shadow-[0_4px_12px_rgba(252,211,77,0.6)]" />
                                </span>
                            </h1>

                            <p className="max-w-2xl text-sm text-white/85 md:text-base animate-fade-right delay-200">
                                {isLeadership
                                    ? "Here's what's moving across the workspace — projects, tasks, spend, and your team's pulse, right now."
                                    : "Your work in flight. Deadlines, momentum, and what's on your plate today."}
                            </p>

                            <div className="flex flex-wrap items-center gap-2 pt-2 animate-fade-right delay-300">
                                <RoleBadge role={user.primary_role} size="md" />
                                {can('users.create') && (
                                    <Button asChild size="sm" className="gap-2 bg-white/15 text-white ring-1 ring-white/30 backdrop-blur transition-all hover:scale-[1.03] hover:bg-white/25">
                                        <Link href={route('users.create')}>
                                            <UserPlus className="size-4" /> Invite user
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild size="sm" variant="ghost" className="gap-2 text-white/90 hover:bg-white/10 hover:text-white">
                                    <Link href={route('tasks.index')}>
                                        <Zap className="size-4" /> Jump to tasks
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div className="flex flex-col items-end gap-3 animate-fade-left delay-200">
                            <div className="rounded-2xl bg-white/10 px-5 py-3 ring-1 ring-white/20 backdrop-blur-md">
                                <LiveClock />
                            </div>
                            <div className="grid grid-cols-3 gap-2 sm:flex sm:gap-2">
                                <HeroPill icon={Activity} label="Active" tone="from-emerald-300 to-teal-300" />
                                <HeroPill icon={TrendingUp} label="On track" tone="from-amber-200 to-orange-300" />
                                <HeroPill icon={Radio} label="Realtime" tone="from-pink-300 to-fuchsia-300" />
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {props.kpis.map((kpi, i) => {
                        const trend = props.burndown ?? props.velocity ?? [];
                        const series = trend.length
                            ? (i % 2 === 0 ? trend.map((p) => p.completed) : trend.map((p) => p.created))
                            : undefined;
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
                    <SoftCard className="lg:col-span-2 animate-fade-up delay-300">
                        <SoftCardTitle eyebrow="Pipeline">Upcoming deadlines</SoftCardTitle>
                        <SoftCardBody>
                            {(props.upcomingDeadlines ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-xs">Nothing due soon.</p>
                            ) : (
                                <ul className="divide-y divide-border/60">
                                    {props.upcomingDeadlines?.map((t, idx) => {
                                        const status = TASK_STATUS_META[t.status];
                                        const priority = TASK_PRIORITY_META[t.priority];
                                        const overdue = isOverdue(t.due_date, t.status);
                                        return (
                                            <li key={t.id} className="animate-fade-right" style={{ animationDelay: `${idx * 60}ms` }}>
                                                <Link
                                                    href={route('tasks.show', t.id)}
                                                    className="group hover:bg-muted/40 flex items-center gap-3 rounded-xl px-2 py-3 transition-colors"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-semibold group-hover:text-violet-600 dark:group-hover:text-violet-300">{t.title}</p>
                                                        <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-[11px]">
                                                            {t.project && (
                                                                <>
                                                                    <span className={cn('size-1.5 rounded-full', COLOR_DOT[t.project.color] ?? 'bg-violet-500')} />
                                                                    <span className="truncate">{t.project.title}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset', priority?.chip)}>
                                                        {priority?.label}
                                                    </span>
                                                    <span className={cn('inline-flex items-center gap-1 text-[11px] font-semibold', overdue && 'text-rose-600 dark:text-rose-400')}>
                                                        <CalendarClock className="size-3.5" /> {relativeDue(t.due_date)}
                                                    </span>
                                                    {t.assignee && (
                                                        <span className="from-violet-500 to-indigo-600 ring-card hidden size-6 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white ring-2 sm:inline-flex">
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
                            <ol className="relative space-y-3 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                {(props.recentActivity ?? []).length === 0 && <p className="text-muted-foreground text-xs">No recent activity.</p>}
                                {props.recentActivity?.map((a, i) => (
                                    <li key={a.id} className="relative animate-fade-left" style={{ animationDelay: `${i * 70}ms` }}>
                                        <span
                                            className={cn(
                                                'shadow-soft-xs ring-card absolute -left-6 top-1 size-3 rounded-full ring-2',
                                                i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                                i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
                                                i === 0 && 'animate-pulse-ring',
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
                <SoftCard className="lg:col-span-2 animate-fade-up delay-150">
                    <SoftCardTitle eyebrow="Velocity">Tasks created vs. completed (last 14 days)</SoftCardTitle>
                    <SoftCardBody>
                        <AreaChart
                            points={(props.burndown ?? []).map((p) => ({ label: p.label, created: p.created, completed: p.completed }))}
                            series={[
                                { key: 'created', label: 'Created', tone: 'violet' },
                                { key: 'completed', label: 'Completed', tone: 'emerald' },
                            ]}
                            height={220}
                        />
                        <div className="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                            <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-violet-500" /> Created</span>
                            <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-emerald-500" /> Completed</span>
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
                                          : 'slate',
                            }))}
                            centerValue={`${completion.completion_rate}%`}
                            centerLabel="Tasks done"
                        />
                    </SoftCardBody>
                </SoftCard>
            </section>

            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="lg:col-span-2 animate-fade-up delay-200">
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
                                    <Link href={route('projects.show', p.slug)} className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group block rounded-xl p-3 ring-1">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-3 min-w-0 flex-1">
                                                <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white shadow-soft-xs', COLOR_GRADIENT[p.color])}>
                                                    {p.title.slice(0, 2).toUpperCase()}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold group-hover:text-violet-600 dark:group-hover:text-violet-300">{p.title}</p>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        {p.utilization !== undefined && p.budget !== undefined && p.budget > 0 ? `${p.utilization}% of budget` : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="font-display tabular-nums text-sm font-bold">{p.progress}%</span>
                                        </div>
                                        <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                            <div className={cn('h-full rounded-full bg-gradient-to-r animate-bar-grow', COLOR_GRADIENT[p.color])} style={{ width: `${Math.max(2, p.progress)}%`, animationDelay: '500ms' }} />
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
                                <li key={p.id} className="bg-muted/30 ring-border/60 ring-1 hover-lift flex animate-fade-right items-center gap-3 rounded-xl p-3" style={{ animationDelay: `${400 + i * 80}ms` }}>
                                    <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white shadow-soft-xs ring-2 ring-card',
                                        i === 0 ? 'from-amber-400 to-orange-500'
                                            : i === 1 ? 'from-violet-500 to-indigo-600'
                                            : i === 2 ? 'from-pink-500 to-fuchsia-600'
                                            : 'from-emerald-500 to-teal-600')}>
                                        {p.initials}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">{p.name}</p>
                                        <p className="text-muted-foreground truncate text-[11px]">{p.job_title ?? '—'}</p>
                                    </div>
                                    <span className="bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums ring-1 ring-inset">
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
                <SoftCard className="lg:col-span-2 animate-fade-up delay-150">
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
                                { key: 'remaining', label: 'Remaining', value: Math.max(0, completion.total_tasks - completion.completed_tasks), tone: 'slate' },
                            ]}
                            centerValue={`${completion.completion_rate}%`}
                            centerLabel="Done"
                        />
                    </SoftCardBody>
                </SoftCard>
            </section>

            <section className="grid gap-5 lg:grid-cols-3">
                <SoftCard className="lg:col-span-2 animate-fade-up delay-200">
                    <SoftCardTitle
                        eyebrow="My projects"
                        action={
                            <Button asChild size="sm" variant="soft">
                                <Link href={route('projects.index')}>All <ArrowUpRight className="size-3.5" /></Link>
                            </Button>
                        }
                    >
                        Where you're contributing
                    </SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-3">
                            {(props.myProjects ?? []).map((p, idx) => (
                                <li key={p.id} className="animate-fade-right" style={{ animationDelay: `${300 + idx * 80}ms` }}>
                                    <Link href={route('projects.show', p.slug)} className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group block rounded-xl p-3 ring-1">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-3 min-w-0 flex-1">
                                                <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white shadow-soft-xs', COLOR_GRADIENT[p.color])}>
                                                    {p.title.slice(0, 2).toUpperCase()}
                                                </div>
                                                <p className="truncate text-sm font-semibold group-hover:text-violet-600 dark:group-hover:text-violet-300">{p.title}</p>
                                            </div>
                                            <span className="font-display tabular-nums text-sm font-bold">{p.progress}%</span>
                                        </div>
                                        <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                            <div className={cn('h-full rounded-full bg-gradient-to-r animate-bar-grow', COLOR_GRADIENT[p.color])} style={{ width: `${Math.max(2, p.progress)}%`, animationDelay: '500ms' }} />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                            {(props.myProjects ?? []).length === 0 && <p className="text-muted-foreground text-xs">You haven't been added to any projects yet.</p>}
                        </ul>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard className="animate-fade-up delay-300">
                    <SoftCardTitle eyebrow="Quick actions">Shortcuts</SoftCardTitle>
                    <SoftCardBody className="space-y-2">
                        <QuickLink href={route('tasks.index', { assignee: 'me' })} icon={ListChecks} label="My tasks" tone="from-violet-500 to-indigo-600" />
                        <QuickLink href={route('expenses.create')} icon={Wallet} label="Submit expense" tone="from-amber-400 to-orange-500" />
                        <QuickLink href={route('projects.index')} icon={FolderKanban} label="Browse projects" tone="from-blue-500 to-cyan-600" />
                        <QuickLink href={route('notifications.index')} icon={Sparkles} label="Notifications" tone="from-pink-500 to-fuchsia-600" />
                    </SoftCardBody>
                </SoftCard>
            </section>
        </>
    );
}

function HeroPill({ icon: Icon, label, tone }: { icon: LucideIcon; label: string; tone: string }) {
    return (
        <span className={cn('group inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white ring-1 ring-white/20 backdrop-blur transition-all hover:scale-105 hover:bg-white/20')}>
            <span className={cn('inline-flex size-5 items-center justify-center rounded-full bg-gradient-to-br shadow-soft-xs', tone)}>
                <Icon className="size-3 text-slate-900/80" />
            </span>
            {label}
        </span>
    );
}

function QuickLink({ href, icon: Icon, label, tone }: { href: string; icon: LucideIcon; label: string; tone: string }) {
    return (
        <Link href={href} className="bg-muted/30 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm hover-lift group flex items-center gap-3 rounded-xl p-3 ring-1">
            <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-soft-xs transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3', tone)}>
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
