import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import { useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Insights', href: '/reports' },
    { title: 'Cumulative flow', href: '/reports/flow' },
];

interface Stage {
    key: string;
    name: string;
    color: string;
}

interface Props {
    days: number;
    project: string | null;
    flow: {
        days: string[];
        stages: Stage[];
        series: Array<Record<string, string | number>>;
    };
    projects: Array<{ value: string; label: string }>;
}

/**
 * Validated categorical fills, ordered so adjacent bands stay distinguishable
 * for colour-blind readers as well as everyone else.
 */
const BAND: Record<string, string> = {
    slate: '#94a3b8',
    blue: '#2a78d6',
    sky: '#5598e7',
    violet: '#4a3aa7',
    emerald: '#1baf7a',
    amber: '#eda100',
    rose: '#e34948',
    pink: '#e87ba4',
};

const FALLBACK = ['#2a78d6', '#eda100', '#1baf7a', '#4a3aa7', '#e34948', '#5598e7', '#e87ba4', '#94a3b8'];

const RANGES = [14, 30, 60, 90];

export default function CumulativeFlow({ days, project, flow, projects }: Props) {
    const { stages, series } = flow;

    const colourFor = (stage: Stage, i: number) => BAND[stage.color] ?? FALLBACK[i % FALLBACK.length];

    // The chart is a stacked area: each day is a column of bands whose heights
    // are the counts, scaled to the busiest day in the window.
    const peak = useMemo(() => Math.max(1, ...series.map((row) => stages.reduce((sum, s) => sum + Number(row[s.key] ?? 0), 0))), [series, stages]);

    const latest = series[series.length - 1];
    const totalNow = stages.reduce((sum, s) => sum + Number(latest?.[s.key] ?? 0), 0);

    const go = (next: { days?: number; project?: string | null }) => {
        router.get(
            route('reports.flow'),
            {
                days: next.days ?? days,
                project: (next.project === undefined ? project : next.project) || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cumulative flow" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Insights"
                    title="Cumulative flow"
                    description="How much work has been sitting in each stage, day by day. Widening bands mean a queue is building."
                    actions={
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('reports.index')}>
                                <BarChart3 className="size-4" />
                                All reports
                            </Link>
                        </Button>
                    }
                />

                <SoftCard>
                    <SoftCardBody className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1">
                            {RANGES.map((range) => (
                                <Button
                                    key={range}
                                    size="sm"
                                    variant={range === days ? 'default' : 'ghost'}
                                    onClick={() => go({ days: range })}
                                    className="text-xs"
                                >
                                    {range}d
                                </Button>
                            ))}
                        </div>

                        <select
                            value={project ?? ''}
                            onChange={(e) => go({ project: e.target.value || null })}
                            className="border-border/60 bg-background ml-auto h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Every project</option>
                            {projects.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {p.label}
                                </option>
                            ))}
                        </select>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle>Items in each stage</SoftCardTitle>
                    <SoftCardBody className="pt-0">
                        {totalNow === 0 ? (
                            <p className="text-muted-foreground py-12 text-center text-sm">
                                No work in this window yet. The chart fills in as tasks move between stages.
                            </p>
                        ) : (
                            <>
                                <div className="scrollbar-soft overflow-x-auto">
                                    <div className="flex h-64 min-w-full items-end gap-px" style={{ minWidth: `${series.length * 10}px` }}>
                                        {series.map((row) => {
                                            const total = stages.reduce((sum, s) => sum + Number(row[s.key] ?? 0), 0);

                                            return (
                                                <div
                                                    key={String(row.date)}
                                                    title={`${row.label}: ${total} item${total === 1 ? '' : 's'}`}
                                                    className="flex h-full flex-1 flex-col justify-end"
                                                >
                                                    {stages.map((stage, i) => {
                                                        const value = Number(row[stage.key] ?? 0);
                                                        if (value === 0) return null;

                                                        return (
                                                            <div
                                                                key={stage.key}
                                                                style={{
                                                                    height: `${(value / peak) * 100}%`,
                                                                    backgroundColor: colourFor(stage, i),
                                                                }}
                                                                title={`${stage.name}: ${value}`}
                                                            />
                                                        );
                                                    })}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="text-muted-foreground mt-2 flex justify-between text-[11px]">
                                    <span>{series[0]?.label}</span>
                                    <span>{latest?.label}</span>
                                </div>

                                {/* Two or more bands, so a legend is not optional. */}
                                <div className="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                    {stages.map((stage, i) => (
                                        <span key={stage.key} className="flex items-center gap-1.5">
                                            <span className="size-2.5 rounded-sm" style={{ backgroundColor: colourFor(stage, i) }} aria-hidden />
                                            {stage.name}
                                            <span className="text-muted-foreground tabular-nums">{Number(latest?.[stage.key] ?? 0)}</span>
                                        </span>
                                    ))}
                                </div>
                            </>
                        )}
                    </SoftCardBody>
                </SoftCard>

                {/* Identity is never colour-alone: the same data, as a table. */}
                <SoftCard>
                    <SoftCardTitle>Today by stage</SoftCardTitle>
                    <SoftCardBody className="overflow-x-auto pt-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-border/60 text-muted-foreground border-b text-left text-[11px] font-bold tracking-[0.12em] uppercase">
                                    <th className="py-2 pr-3">Stage</th>
                                    <th className="py-2 pr-3 text-right">Now</th>
                                    <th className="py-2 pr-3 text-right">Start of window</th>
                                    <th className="py-2 text-right">Change</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border/60 divide-y">
                                {stages.map((stage) => {
                                    const now = Number(latest?.[stage.key] ?? 0);
                                    const then = Number(series[0]?.[stage.key] ?? 0);
                                    const delta = now - then;

                                    return (
                                        <tr key={stage.key}>
                                            <td className="py-2.5 pr-3 font-medium">{stage.name}</td>
                                            <td className="py-2.5 pr-3 text-right tabular-nums">{now}</td>
                                            <td className="text-muted-foreground py-2.5 pr-3 text-right tabular-nums">{then}</td>
                                            <td
                                                className={cn(
                                                    'py-2.5 text-right tabular-nums',
                                                    delta > 0 && 'text-amber-600',
                                                    delta < 0 && 'text-emerald-600',
                                                )}
                                            >
                                                {delta > 0 ? `+${delta}` : delta}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </SoftCardBody>
                </SoftCard>
            </div>
        </AppLayout>
    );
}
