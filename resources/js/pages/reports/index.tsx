import { AreaChart } from '@/components/charts/area-chart';
import { DonutChart } from '@/components/charts/donut-chart';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { COLOR_GRADIENT, type ProjectColor, PRIORITY_META, STATUS_META } from '@/lib/projects';
import { TASK_PRIORITY_META, TASK_STATUS_META, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { EXPENSE_CATEGORY_META, formatMoney, type ExpenseCategory } from '@/lib/expenses';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    AlertCircle,
    BarChart3,
    CheckCircle2,
    FolderKanban,
    ListChecks,
    Receipt,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';

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
    overview: {
        projects_total: number;
        projects_active: number;
        projects_completed: number;
        tasks_total: number;
        tasks_completed: number;
        tasks_overdue: number;
        expenses_pending: number;
        expenses_approved: number;
        budget_total: number;
        users_active: number;
        created_projects_in_range: number;
        completed_tasks_in_range: number;
    };
    projectsByStatus: Array<{ key: string; value: number }>;
    tasksByStatus: Array<{ key: TaskStatus; value: number }>;
    tasksByPriority: Array<{ key: TaskPriority; value: number }>;
    expensesByCategory: Array<{ key: ExpenseCategory; count: number; amount: number }>;
    expensesTrend: Array<{ label: string; submitted: number; approved: number }>;
    taskTrend: Array<{ label: string; created: number; completed: number }>;
    topPerformers: Array<{ id: number; name: string; initials: string; job_title: string | null; completed_tasks: number }>;
    projectHealth: Array<{
        id: number;
        slug: string;
        title: string;
        color: ProjectColor;
        status: string;
        progress: number;
        budget: number;
        spent: number;
        currency: string;
        utilization: number;
        over_budget: boolean;
        overdue: boolean;
        owner: string | null;
    }>;
    recentActivity: Array<{ id: number; action: string; description: string | null; module: string | null; created_at: string; actor: { id: number; name: string } | null }>;
}

export default function ReportsIndex(props: ReportProps) {
    const getInitials = useInitials();
    const completionRate = props.overview.tasks_total > 0
        ? Math.round((props.overview.tasks_completed / props.overview.tasks_total) * 100)
        : 0;

    const setRange = (r: number) => {
        router.get(route('reports.index'), { range: r }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Insights"
                    title="Reports & Analytics"
                    description="Cross-workspace performance: project velocity, task throughput, financial health, and team output."
                    actions={
                        <div className="bg-card ring-border/60 shadow-soft-xs flex gap-1 rounded-xl p-1 ring-1">
                            {RANGES.map((r) => (
                                <button
                                    key={r.value}
                                    onClick={() => setRange(r.value)}
                                    className={cn(
                                        'inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold transition-all',
                                        props.range === r.value
                                            ? 'shadow-soft-sm from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {r.label}
                                </button>
                            ))}
                        </div>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Active projects" value={props.overview.projects_active} sub={`${props.overview.projects_total} total · ${props.overview.projects_completed} done`} icon={FolderKanban} accent="violet" />
                    <StatCard label="Task completion" value={`${completionRate}%`} sub={`${props.overview.tasks_completed} / ${props.overview.tasks_total} · ${props.overview.tasks_overdue} overdue`} icon={ListChecks} accent="emerald" />
                    <StatCard label="Approved spend" value={formatMoney(props.overview.expenses_approved)} sub={`${formatMoney(props.overview.expenses_pending)} pending · ${formatMoney(props.overview.budget_total)} total budget`} icon={Wallet} accent="amber" />
                    <StatCard label="Active members" value={props.overview.users_active} sub={`${props.overview.created_projects_in_range} new projects · ${props.overview.completed_tasks_in_range} tasks done in range`} icon={Users} accent="blue" />
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
                                <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-violet-500" /> Created</span>
                                <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-emerald-500" /> Completed</span>
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
                                    tone: s.key === 'completed' ? 'violet' : s.key === 'active' ? 'emerald' : s.key === 'on_hold' ? 'amber' : s.key === 'planning' ? 'blue' : 'slate',
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
                                    const meta = TASK_STATUS_META[row.key];
                                    return (
                                        <li key={row.key} className="bg-muted/30 ring-border/60 flex items-center justify-between gap-3 rounded-xl p-3 ring-1">
                                            <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', meta?.chip)}>
                                                {meta?.label ?? row.key}
                                            </span>
                                            <span className="font-display tabular-nums text-sm font-bold">{row.value}</span>
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
                                        <li key={row.key} className="bg-muted/30 ring-border/60 flex items-center justify-between gap-3 rounded-xl p-3 ring-1">
                                            <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', meta?.chip)}>
                                                {meta?.label ?? row.key}
                                            </span>
                                            <span className="font-display tabular-nums text-sm font-bold">{row.value}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Performance">Top performers</SoftCardTitle>
                        <SoftCardBody>
                            {props.topPerformers.length === 0 ? (
                                <p className="text-muted-foreground text-xs">No completion activity in this range.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {props.topPerformers.map((p, i) => (
                                        <li key={p.id} className="bg-muted/30 ring-border/60 ring-1 flex items-center gap-3 rounded-xl p-3">
                                            <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white shadow-soft-xs ring-2 ring-card',
                                                i === 0 ? 'from-amber-400 to-orange-500'
                                                    : i === 1 ? 'from-violet-500 to-indigo-600'
                                                    : i === 2 ? 'from-pink-500 to-fuchsia-600'
                                                    : 'from-emerald-500 to-teal-600')}>
                                                {p.initials || getInitials(p.name)}
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
                                </ul>
                            )}
                        </SoftCardBody>
                    </SoftCard>
                </section>

                <section className="grid gap-5 lg:grid-cols-3">
                    <SoftCard className="lg:col-span-2">
                        <SoftCardTitle eyebrow="Finance">Spending trend (submitted vs. approved)</SoftCardTitle>
                        <SoftCardBody>
                            <AreaChart
                                points={props.expensesTrend.map((p) => ({ label: p.label, submitted: p.submitted, approved: p.approved }))}
                                series={[
                                    { key: 'submitted', label: 'Submitted', tone: 'amber' },
                                    { key: 'approved', label: 'Approved', tone: 'emerald' },
                                ]}
                                height={220}
                            />
                            <div className="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                                <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-amber-500" /> Submitted</span>
                                <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-emerald-500" /> Approved</span>
                            </div>
                        </SoftCardBody>
                    </SoftCard>

                    <SoftCard>
                        <SoftCardTitle eyebrow="Finance">Spend by category</SoftCardTitle>
                        <SoftCardBody>
                            <ul className="space-y-2">
                                {props.expensesByCategory.filter((c) => c.amount > 0).slice(0, 8).map((c) => {
                                    const meta = EXPENSE_CATEGORY_META[c.key];
                                    return (
                                        <li key={c.key} className="flex items-center justify-between gap-2">
                                            <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', meta?.chip)}>
                                                {meta?.label ?? c.key}
                                            </span>
                                            <div className="text-right">
                                                <p className="font-display text-sm font-bold tabular-nums">{formatMoney(c.amount)}</p>
                                                <p className="text-muted-foreground text-[10px]">{c.count} expense{c.count === 1 ? '' : 's'}</p>
                                            </div>
                                        </li>
                                    );
                                })}
                                {props.expensesByCategory.every((c) => c.amount === 0) && (
                                    <p className="text-muted-foreground text-xs">No spend recorded in this range.</p>
                                )}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>
                </section>

                <SoftCard>
                    <SoftCardTitle eyebrow="Health">Project portfolio</SoftCardTitle>
                    <SoftCardBody className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b border-border/60 text-left text-[10px] font-bold uppercase tracking-[0.14em]">
                                    <th className="py-2 pr-3">Project</th>
                                    <th className="py-2 pr-3">Owner</th>
                                    <th className="py-2 pr-3">Status</th>
                                    <th className="py-2 pr-3">Progress</th>
                                    <th className="py-2 pr-3 text-right">Budget</th>
                                    <th className="py-2 pr-3 text-right">Spent</th>
                                    <th className="py-2 text-right">Health</th>
                                </tr>
                            </thead>
                            <tbody>
                                {props.projectHealth.length === 0 && (
                                    <tr><td colSpan={7} className="text-muted-foreground py-6 text-center text-xs">No projects yet.</td></tr>
                                )}
                                {props.projectHealth.map((p) => {
                                    const status = STATUS_META[p.status as keyof typeof STATUS_META];
                                    return (
                                        <tr key={p.id} className="border-b border-border/40 last:border-0">
                                            <td className="py-3 pr-3">
                                                <Link href={route('projects.show', p.slug)} className="group inline-flex items-center gap-2 hover:text-violet-600 dark:hover:text-violet-300">
                                                    <span className={cn('size-6 rounded-md bg-gradient-to-br text-[10px] font-bold text-white inline-flex items-center justify-center shadow-soft-xs', COLOR_GRADIENT[p.color])}>
                                                        {p.title.slice(0, 2).toUpperCase()}
                                                    </span>
                                                    <span className="font-semibold">{p.title}</span>
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground py-3 pr-3 text-xs">{p.owner ?? '—'}</td>
                                            <td className="py-3 pr-3">
                                                <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset', status?.chip)}>
                                                    {status?.label ?? p.status}
                                                </span>
                                            </td>
                                            <td className="py-3 pr-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-muted h-1.5 w-24 overflow-hidden rounded-full">
                                                        <div className={cn('h-full bg-gradient-to-r', COLOR_GRADIENT[p.color])} style={{ width: `${Math.max(2, p.progress)}%` }} />
                                                    </div>
                                                    <span className="text-xs font-bold tabular-nums">{p.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3 text-right tabular-nums">{formatMoney(p.budget, p.currency)}</td>
                                            <td className="py-3 pr-3 text-right tabular-nums">{formatMoney(p.spent, p.currency)}</td>
                                            <td className="py-3 text-right">
                                                {p.over_budget ? (
                                                    <span className="bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset">
                                                        <AlertCircle className="size-3" /> Over budget
                                                    </span>
                                                ) : p.overdue ? (
                                                    <span className="bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset">
                                                        Overdue
                                                    </span>
                                                ) : (
                                                    <span className="bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset">
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
                    <SoftCardTitle eyebrow="Audit" action={<Button asChild size="sm" variant="soft" className="gap-1.5"><Link href={route('activity.index')}><BarChart3 className="size-3.5" /> Full log</Link></Button>}>Recent activity</SoftCardTitle>
                    <SoftCardBody>
                        {props.recentActivity.length === 0 ? (
                            <p className="text-muted-foreground text-xs">No activity recorded.</p>
                        ) : (
                            <ol className="relative space-y-3 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                {props.recentActivity.map((a, i) => (
                                    <li key={a.id} className="relative">
                                        <span
                                            className={cn(
                                                'shadow-soft-xs ring-card absolute -left-6 top-1 size-3 rounded-full ring-2',
                                                i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                                i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
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
