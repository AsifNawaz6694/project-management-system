import { AreaChart } from '@/components/charts/area-chart';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, ListChecks, Target, TrendingUp } from 'lucide-react';

interface Totals {
    total_points: number;
    completed_points: number;
    total_tasks: number;
    completed_tasks: number;
}

interface Props {
    project: { id: number; slug: string; title: string };
    active: {
        id: number;
        name: string;
        goal: string | null;
        starts_at: string | null;
        ends_at: string | null;
        totals: Totals;
        burndown: Array<{ date: string; remaining: number | null; ideal: number | null }>;
    } | null;
    velocity: Array<{ sprint: string; committed: number; completed: number }>;
    completed: Array<{ id: number; name: string; completed_at: string | null; totals: Totals }>;
}

const shortDate = (iso: string) => new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

export default function SprintReport({ project, active, velocity, completed }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.title, href: `/projects/${project.slug}` },
        { title: 'Sprint report', href: `/projects/${project.slug}/sprint-report` },
    ];

    // The burndown carries two series, so it needs a legend either way; the
    // ideal line is what makes the actual line mean anything.
    const burndownPoints = (active?.burndown ?? []).map((d) => ({
        label: shortDate(d.date),
        remaining: d.remaining ?? 0,
        ideal: d.ideal ?? 0,
    }));

    const peakVelocity = Math.max(1, ...velocity.flatMap((v) => [v.committed, v.completed]));
    const average = velocity.length > 0 ? velocity.reduce((sum, v) => sum + v.completed, 0) / velocity.length : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${project.title} — sprint report`} />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Insights"
                    title="Sprint report"
                    description="How the current sprint is tracking, and how much this team actually delivers."
                    actions={
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('sprints.backlog', project.slug)}>Back to backlog</Link>
                        </Button>
                    }
                />

                {active ? (
                    <>
                        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Sprint" value={active.name} icon={Target} accent="violet" />
                            <StatCard
                                label="Points done"
                                value={`${active.totals.completed_points} / ${active.totals.total_points}`}
                                icon={CheckCircle2}
                                accent="emerald"
                            />
                            <StatCard
                                label="Items done"
                                value={`${active.totals.completed_tasks} / ${active.totals.total_tasks}`}
                                icon={ListChecks}
                                accent="blue"
                            />
                            <StatCard label="Average velocity" value={average.toFixed(1)} icon={TrendingUp} accent="amber" />
                        </section>

                        <SoftCard>
                            <SoftCardTitle>Burndown — points remaining</SoftCardTitle>
                            <SoftCardBody className="pt-0">
                                {burndownPoints.length > 1 ? (
                                    <AreaChart
                                        points={burndownPoints}
                                        series={[
                                            { key: 'remaining', label: 'Remaining', tone: 'blue' },
                                            { key: 'ideal', label: 'Ideal', tone: 'slate' },
                                        ]}
                                        height={220}
                                    />
                                ) : (
                                    <p className="text-muted-foreground py-8 text-center text-sm">
                                        The burndown fills in as the sprint runs — one reading per day.
                                    </p>
                                )}
                                {active.goal && <p className="text-muted-foreground mt-2 text-xs">Goal: {active.goal}</p>}
                            </SoftCardBody>
                        </SoftCard>
                    </>
                ) : (
                    <SoftCard>
                        <SoftCardBody className="text-muted-foreground py-10 text-center text-sm">
                            No sprint is running. Start one from the{' '}
                            <Link href={route('sprints.backlog', project.slug)} className="underline">
                                backlog
                            </Link>
                            .
                        </SoftCardBody>
                    </SoftCard>
                )}

                <SoftCard>
                    <SoftCardTitle>Velocity — committed against delivered</SoftCardTitle>
                    <SoftCardBody className="pt-0">
                        {velocity.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">Velocity appears once a sprint has been completed.</p>
                        ) : (
                            <>
                                <div className="mb-3 flex items-center gap-4 text-xs">
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-full bg-slate-300" />
                                        Committed
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-full bg-emerald-600" />
                                        Delivered
                                    </span>
                                </div>

                                <div className="flex flex-col gap-2.5">
                                    {velocity.map((row) => (
                                        <div key={row.sprint} className="flex items-center gap-3 text-xs">
                                            <span className="w-24 shrink-0 truncate font-medium">{row.sprint}</span>
                                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                                <div className="bg-muted/50 h-2 w-full overflow-hidden rounded-full">
                                                    <div
                                                        className="h-full rounded-full bg-slate-300"
                                                        style={{ width: `${(row.committed / peakVelocity) * 100}%` }}
                                                    />
                                                </div>
                                                <div className="bg-muted/50 h-2 w-full overflow-hidden rounded-full">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-600"
                                                        style={{ width: `${(row.completed / peakVelocity) * 100}%` }}
                                                    />
                                                </div>
                                            </div>
                                            <span className="w-20 shrink-0 text-right tabular-nums">
                                                {row.completed} / {row.committed}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <p className="text-muted-foreground mt-3 text-xs">
                                    Averaging <strong className="text-foreground">{average.toFixed(1)}</strong> points per sprint across the last{' '}
                                    {velocity.length}.
                                </p>
                            </>
                        )}
                    </SoftCardBody>
                </SoftCard>

                {completed.length > 0 && (
                    <SoftCard>
                        <SoftCardTitle>Completed sprints</SoftCardTitle>
                        <SoftCardBody className="overflow-x-auto pt-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-border/60 text-muted-foreground border-b text-left text-[11px] font-bold tracking-[0.12em] uppercase">
                                        <th className="py-2 pr-3">Sprint</th>
                                        <th className="py-2 pr-3">Completed</th>
                                        <th className="py-2 pr-3 text-right">Items</th>
                                        <th className="py-2 text-right">Points</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border/60 divide-y">
                                    {completed.map((sprint) => (
                                        <tr key={sprint.id}>
                                            <td className="py-2.5 pr-3 font-medium">{sprint.name}</td>
                                            <td className="text-muted-foreground py-2.5 pr-3">
                                                {sprint.completed_at ? shortDate(sprint.completed_at) : '—'}
                                            </td>
                                            <td className={cn('py-2.5 pr-3 text-right tabular-nums')}>
                                                {sprint.totals.completed_tasks} / {sprint.totals.total_tasks}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {sprint.totals.completed_points} / {sprint.totals.total_points}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </SoftCardBody>
                    </SoftCard>
                )}
            </div>
        </AppLayout>
    );
}
