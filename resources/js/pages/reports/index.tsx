import { AreaChart } from '@/components/charts/area-chart';
import { DonutChart } from '@/components/charts/donut-chart';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_GRADIENT, STATUS_META, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, fallbackStatus, statusChip, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AreaChart as AreaChartIcon, BarChart3, CheckCircle2, Download, FolderKanban, ListChecks, Printer, TrendingUp, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Insights', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

const RANGES: Array<{ value: number; label: string }> = [
    { value: 7, label: 'Last 7 days' },
    { value: 30, label: 'Last 30 days' },
    { value: 90, label: 'Last quarter' },
    { value: 180, label: 'Last 6 months' },
];

interface ReportProps {
    range: number;
    /**
     * True when the viewer lacks `reports.view-all`: every figure on the page
     * covers their own work only, and the cards that rank colleagues are gone.
     */
    scoped: boolean;
    overview: {
        projects_total: number;
        projects_active: number;
        projects_completed: number;
        tasks_total: number;
        tasks_completed: number;
        tasks_open: number;
        tasks_overdue: number;
        users_active: number;
        created_projects_in_range: number;
        completed_tasks_in_range: number;
    };
    projectsByStatus: Array<{ key: string; value: number }>;
    tasksByStatus: Array<{ key: TaskStatus; value: number }>;
    tasksByPriority: Array<{ key: TaskPriority; value: number }>;
    taskTrend: Array<{ label: string; created: number; completed: number }>;
    topPerformers: Array<{ id: number; name: string; initials: string; job_title: string | null; completed_tasks: number }>;
    flow: { lead_time_hours: number | null; cycle_time_hours: number | null; overdue_rate: number };
    aging: Array<{ label: string; value: number }>;
    workloadByUser: Array<{
        id: number;
        name: string;
        job_title: string | null;
        open_tasks: number;
        estimated_minutes: number;
        overdue_tasks: number;
    }>;
    workloadByTeam: Array<{ id: number; name: string; color: string; open_tasks: number; estimated_minutes: number }>;
    projectHealth: Array<{
        id: number;
        slug: string;
        title: string;
        color: ProjectColor;
        status: string;
        progress: number;
        overdue: boolean;
        owner: string | null;
    }>;
    recentActivity: Array<{
        id: number;
        action: string;
        description: string | null;
        module: string | null;
        created_at: string;
        actor: { id: number; name: string } | null;
    }>;
}

export default function ReportsIndex(props: ReportProps) {
    const { can } = usePermissions();
    const getInitials = useInitials();
    const completionRate = props.overview.tasks_total > 0 ? Math.round((props.overview.tasks_completed / props.overview.tasks_total) * 100) : 0;

    const setRange = (r: number) => {
        router.get(route('reports.index'), { range: r }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Insights"
                    title={props.scoped ? 'My Reports' : 'Reports & Analytics'}
                    description={
                        props.scoped
                            ? 'Your own performance: the tasks assigned to you and the projects you are working in.'
                            : 'Cross-workspace performance: project velocity, task throughput, and team output.'
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can('reports.export') && (
                                <>
                                    <Button size="sm" variant="outline" asChild>
                                        <a href={route('reports.export.tasks')}>
                                            <Download className="size-3.5" />
                                            CSV
                                        </a>
                                    </Button>
                                    <Button size="sm" variant="outline" asChild>
                                        <a href={route('reports.print')} target="_blank" rel="noreferrer">
                                            <Printer className="size-3.5" />
                                            Print
                                        </a>
                                    </Button>
                                </>
                            )}
                            <Button size="sm" variant="outline" asChild>
                                <Link href={route('reports.flow')}>
                                    <AreaChartIcon className="size-3.5" />
                                    Flow
                                </Link>
                            </Button>
                            <div className="bg-card ring-border/60 shadow-soft-xs flex gap-1 rounded-xl p-1 ring-1">
                                {RANGES.map((r) => (
                                    <button
                                        key={r.value}
                                        onClick={() => setRange(r.value)}
                                        className={cn(
                                            'inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold transition-all',
                                            props.range === r.value
                                                ? 'shadow-soft-sm bg-gradient-to-br from-blue-600 to-blue-700 text-white'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {r.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        label={props.scoped ? 'My active projects' : 'Active projects'}
                        value={props.overview.projects_active}
                        sub={`${props.overview.projects_total} total · ${props.overview.projects_completed} done`}
                        icon={FolderKanban}
                        accent="violet"
                    />
                    <StatCard
                        label="Task completion"
                        value={`${completionRate}%`}
                        sub={`${props.overview.tasks_completed} / ${props.overview.tasks_total} · ${props.overview.tasks_overdue} overdue`}
                        icon={ListChecks}
                        accent="emerald"
                    />
                    {props.scoped ? (
                        <StatCard
                            label="My open tasks"
                            value={props.overview.tasks_open}
                            sub={`${props.overview.completed_tasks_in_range} completed in range`}
                            icon={TrendingUp}
                            accent="blue"
                        />
                    ) : (
                        <StatCard
                            label="Active members"
                            value={props.overview.users_active}
                            sub={`${props.overview.created_projects_in_range} new projects · ${props.overview.completed_tasks_in_range} tasks done in range`}
                            icon={Users}
                            accent="blue"
                        />
                    )}
                </section>

                <section className="grid gap-5 lg:grid-cols-3">
                    <SoftCard className="lg:col-span-2">
                        <SoftCardTitle eyebrow="Velocity">Tasks created vs. completed</SoftCardTitle>
                        <SoftCardBody>
                            <AreaChart
                                points={props.taskTrend.map((p) => ({ label: p.label, created: p.created, completed: p.completed }))}
                                series={[
                                    { key: 'created', label: 'Created', tone: 'violet' },
                                    { key: 'completed', label: 'Completed', tone: 'emerald' },
                                ]}
                                height={220}
                            />
                            <div className="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-blue-500" /> Created
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-emerald-500" /> Completed
                                </span>
                            </div>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Distribution">Project status</SoftCardTitle>
                        <SoftCardBody>
                            <DonutChart
                                slices={props.projectsByStatus.map((s) => ({
                                    key: s.key,
                                    label: STATUS_META[s.key as keyof typeof STATUS_META]?.label ?? s.key,
                                    value: s.value,
                                    tone:
                                        s.key === 'completed'
                                            ? 'violet'
                                            : s.key === 'active'
                                              ? 'emerald'
                                              : s.key === 'on_hold'
                                                ? 'amber'
                                                : s.key === 'planning'
                                                  ? 'blue'
                                                  : 'slate',
                                }))}
                                centerValue={String(props.overview.projects_total)}
                                centerLabel="Projects"
                            />
                        </SoftCardBody>
                    </SoftCard>
                </section>

                <section className="grid gap-5 lg:grid-cols-3">
                    <SoftCard>
                        <SoftCardTitle eyebrow="Tasks">By status</SoftCardTitle>
                        <SoftCardBody>
                            <ul className="space-y-2">
                                {props.tasksByStatus.map((row) => {
                                    const meta = fallbackStatus(row.key);
                                    return (
                                        <li
                                            key={row.key}
                                            className="bg-muted/30 ring-border/60 flex items-center justify-between gap-3 rounded-xl p-3 ring-1"
                                        >
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                    statusChip(meta.color),
                                                )}
                                            >
                                                {meta.name}
                                            </span>
                                            <span className="font-display text-sm font-bold tabular-nums">{row.value}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Tasks">By priority</SoftCardTitle>
                        <SoftCardBody>
                            <ul className="space-y-2">
                                {props.tasksByPriority.map((row) => {
                                    const meta = TASK_PRIORITY_META[row.key];
                                    return (
                                        <li
                                            key={row.key}
                                            className="bg-muted/30 ring-border/60 flex items-center justify-between gap-3 rounded-xl p-3 ring-1"
                                        >
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                    meta?.chip,
                                                )}
                                            >
                                                {meta?.label ?? row.key}
                                            </span>
                                            <span className="font-display text-sm font-bold tabular-nums">{row.value}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Flow">Delivery metrics</SoftCardTitle>
                        <SoftCardBody className="space-y-3">
                            <div className="grid grid-cols-3 gap-2">
                                <Metric
                                    label="Lead time"
                                    value={props.flow.lead_time_hours === null ? '—' : formatHours(props.flow.lead_time_hours)}
                                    hint="Created → done"
                                />
                                <Metric
                                    label="Cycle time"
                                    value={props.flow.cycle_time_hours === null ? '—' : formatHours(props.flow.cycle_time_hours)}
                                    hint="Started → done"
                                />
                                <Metric label="Overdue" value={`${props.flow.overdue_rate}%`} hint="Of open work" />
                            </div>

                            <div>
                                <p className="text-muted-foreground mb-1.5 text-[10px] font-bold tracking-[0.12em] uppercase">Aging open work</p>
                                <ul className="space-y-1.5">
                                    {props.aging.map((bucket) => {
                                        const max = Math.max(...props.aging.map((b) => b.value), 1);
                                        return (
                                            <li key={bucket.label} className="flex items-center gap-2 text-xs">
                                                <span className="text-muted-foreground w-20 shrink-0">{bucket.label}</span>
                                                <div className="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                                                    <div
                                                        className="bg-primary h-full rounded-full"
                                                        style={{ width: `${(bucket.value / max) * 100}%` }}
                                                    />
                                                </div>
                                                <span className="w-8 text-right font-semibold tabular-nums">{bucket.value}</span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        </SoftCardBody>
                    </SoftCard>

                    {/* Capacity and ranking compare people, so they only appear on the
                        workspace-wide report. A scoped viewer has their own numbers in
                        the tiles above instead. */}
                    {!props.scoped && (
                        <SoftCard>
                            <SoftCardTitle eyebrow="Capacity">Open workload by person</SoftCardTitle>
                            <SoftCardBody>
                                {props.workloadByUser.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No open work assigned.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {props.workloadByUser.map((w) => (
                                            <li key={w.id} className="bg-muted/30 ring-border/60 flex items-center gap-3 rounded-xl p-2.5 ring-1">
                                                <span className="ring-card flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 text-[10px] font-bold text-white ring-2">
                                                    {getInitials(w.name)}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold">{w.name}</p>
                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                        {w.job_title ?? '—'}
                                                        {w.estimated_minutes > 0 && ` · ${formatHours(w.estimated_minutes / 60)} estimated`}
                                                    </p>
                                                </div>
                                                {w.overdue_tasks > 0 && (
                                                    <span className="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-700 tabular-nums ring-1 ring-red-200/70 ring-inset dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30">
                                                        {w.overdue_tasks} late
                                                    </span>
                                                )}
                                                <span className="bg-muted inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums">
                                                    {w.open_tasks} open
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    )}

                    {props.workloadByTeam.length > 0 && (
                        <SoftCard>
                            <SoftCardTitle eyebrow="Capacity">Open workload by team</SoftCardTitle>
                            <SoftCardBody>
                                <ul className="space-y-2">
                                    {props.workloadByTeam.map((t) => (
                                        <li key={t.id} className="bg-muted/30 ring-border/60 flex items-center gap-3 rounded-xl p-2.5 ring-1">
                                            <span className="min-w-0 flex-1 truncate text-sm font-semibold">{t.name}</span>
                                            {t.estimated_minutes > 0 && (
                                                <span className="text-muted-foreground text-[11px]">
                                                    {formatHours(t.estimated_minutes / 60)} est.
                                                </span>
                                            )}
                                            <span className="bg-muted inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums">
                                                {t.open_tasks} open
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </SoftCardBody>
                        </SoftCard>
                    )}

                    {!props.scoped && (
                        <SoftCard>
                            <SoftCardTitle eyebrow="Performance">Top performers</SoftCardTitle>
                            <SoftCardBody>
                                {props.topPerformers.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No completion activity in this range.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {props.topPerformers.map((p, i) => (
                                            <li key={p.id} className="bg-muted/30 ring-border/60 flex items-center gap-3 rounded-xl p-3 ring-1">
                                                <div
                                                    className={cn(
                                                        'shadow-soft-xs ring-card flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white ring-2',
                                                        i === 0
                                                            ? 'from-amber-400 to-orange-500'
                                                            : i === 1
                                                              ? 'from-blue-500 to-blue-700'
                                                              : i === 2
                                                                ? 'from-slate-500 to-slate-700'
                                                                : 'from-emerald-500 to-teal-600',
                                                    )}
                                                >
                                                    {p.initials || getInitials(p.name)}
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
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    )}
                </section>

                <SoftCard>
                    <SoftCardTitle eyebrow="Health">Project portfolio</SoftCardTitle>
                    <SoftCardBody className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-border/60 border-b text-left text-[10px] font-bold tracking-[0.14em] uppercase">
                                    <th className="py-2 pr-3">Project</th>
                                    <th className="py-2 pr-3">Owner</th>
                                    <th className="py-2 pr-3">Status</th>
                                    <th className="py-2 pr-3">Progress</th>
                                    <th className="py-2 text-right">Health</th>
                                </tr>
                            </thead>
                            <tbody>
                                {props.projectHealth.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground py-6 text-center text-xs">
                                            No projects yet.
                                        </td>
                                    </tr>
                                )}
                                {props.projectHealth.map((p) => {
                                    const status = STATUS_META[p.status as keyof typeof STATUS_META];
                                    return (
                                        <tr key={p.id} className="border-border/40 border-b last:border-0">
                                            <td className="py-3 pr-3">
                                                <Link
                                                    href={route('projects.show', p.slug)}
                                                    className="group inline-flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-300"
                                                >
                                                    <span
                                                        className={cn(
                                                            'shadow-soft-xs inline-flex size-6 items-center justify-center rounded-md bg-gradient-to-br text-[10px] font-bold text-white',
                                                            COLOR_GRADIENT[p.color],
                                                        )}
                                                    >
                                                        {p.title.slice(0, 2).toUpperCase()}
                                                    </span>
                                                    <span className="font-semibold">{p.title}</span>
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground py-3 pr-3 text-xs">{p.owner ?? '—'}</td>
                                            <td className="py-3 pr-3">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                                                        status?.chip,
                                                    )}
                                                >
                                                    {status?.label ?? p.status}
                                                </span>
                                            </td>
                                            <td className="py-3 pr-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-muted h-1.5 w-24 overflow-hidden rounded-full">
                                                        <div
                                                            className={cn('h-full bg-gradient-to-r', COLOR_GRADIENT[p.color])}
                                                            style={{ width: `${Math.max(2, p.progress)}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs font-bold tabular-nums">{p.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="py-3 text-right">
                                                {p.overdue ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-200/70 ring-inset dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
                                                        Overdue
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200/70 ring-inset dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30">
                                                        <TrendingUp className="size-3" /> Healthy
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Audit"
                        action={
                            // The full log is an administration surface; offering the
                            // link to someone the route would refuse is worse than
                            // not offering it.
                            can('users.view') ? (
                                <Button asChild size="sm" variant="soft" className="gap-1.5">
                                    <Link href={route('activity.index')}>
                                        <BarChart3 className="size-3.5" /> Full log
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    >
                        Recent activity
                    </SoftCardTitle>
                    <SoftCardBody>
                        {props.recentActivity.length === 0 ? (
                            <p className="text-muted-foreground text-xs">No activity recorded.</p>
                        ) : (
                            <ol className="before:bg-border relative space-y-3 pl-6 before:absolute before:top-1.5 before:bottom-1.5 before:left-2 before:w-px">
                                {props.recentActivity.map((a, i) => (
                                    <li key={a.id} className="relative">
                                        <span
                                            className={cn(
                                                'shadow-soft-xs ring-card absolute top-1 -left-6 size-3 rounded-full ring-2',
                                                i % 4 === 0 && 'bg-gradient-to-br from-blue-500 to-blue-700',
                                                i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                i % 4 === 3 && 'bg-gradient-to-br from-slate-500 to-slate-700',
                                            )}
                                        />
                                        <p className="text-sm">{a.description ?? a.action}</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            {a.actor?.name ? `${a.actor.name} · ` : ''}
                                            {new Date(a.created_at).toLocaleString()}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </SoftCardBody>
                </SoftCard>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value, hint }: { label: string; value: string; hint: string }) {
    return (
        <div className="bg-muted/30 ring-border/60 rounded-xl p-2.5 ring-1">
            <p className="text-muted-foreground text-[10px] font-bold tracking-[0.12em] uppercase">{label}</p>
            <p className="font-display mt-0.5 text-xl font-bold tabular-nums">{value}</p>
            <p className="text-muted-foreground text-[10px]">{hint}</p>
        </div>
    );
}

function formatHours(hours: number): string {
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    if (hours < 24) return `${hours.toFixed(1)}h`;
    return `${(hours / 24).toFixed(1)}d`;
}
