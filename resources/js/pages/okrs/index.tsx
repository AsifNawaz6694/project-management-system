import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus, Target, TrendingUp } from 'lucide-react';

interface UserMini { id: number; name: string }
interface KeyResultMini { id: number; title: string; status: string; current_value: string | number; target_value: string | number; unit: string | null; progress: number }
interface ObjectiveRow {
    id: number;
    title: string;
    period: string;
    status: string;
    visibility: string;
    progress: number;
    owner: UserMini | null;
    team: { id: number; name: string; color: string } | null;
    project: { id: number; slug: string; title: string } | null;
    key_results: KeyResultMini[];
}

interface Props { objectives: ObjectiveRow[]; periods: string[]; period: string | null }

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'OKRs', href: '/okrs' },
];

const STATUS_TONE: Record<string, string> = {
    on_track: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 ring-emerald-200',
    at_risk: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 ring-amber-200',
    off_track: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 ring-rose-200',
    achieved: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300 ring-violet-200',
};

export default function OkrIndex({ objectives, periods, period }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="OKRs" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Goals"
                    title="OKRs"
                    description="Set ambitious objectives and measurable key results. Track progress at a glance."
                    actions={<Button asChild className="gap-2"><Link href={route('okrs.create')}><Plus className="size-4" /> New objective</Link></Button>}
                />

                {periods.length > 0 && (
                    <div className="bg-muted/40 ring-border/60 ring-1 inline-flex w-fit gap-1 rounded-xl p-1">
                        <Link href={route('okrs.index')} className={cn('rounded-lg px-3 py-1.5 text-xs font-semibold transition-all', !period ? 'bg-card shadow-soft-xs text-foreground' : 'text-muted-foreground hover:text-foreground')}>
                            All
                        </Link>
                        {periods.map((p) => (
                            <Link key={p} href={route('okrs.index', { period: p })} className={cn('rounded-lg px-3 py-1.5 text-xs font-semibold transition-all', period === p ? 'bg-card shadow-soft-xs text-foreground' : 'text-muted-foreground hover:text-foreground')}>
                                {p}
                            </Link>
                        ))}
                    </div>
                )}

                {objectives.length === 0 ? (
                    <SoftCard><SoftCardBody><p className="text-muted-foreground text-sm">No objectives yet.</p></SoftCardBody></SoftCard>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {objectives.map((o) => (
                            <SoftCard key={o.id}>
                                <SoftCardTitle
                                    eyebrow={`${o.period} · ${o.status}`}
                                    action={
                                        <Link href={route('okrs.show', o.id)} className="text-violet-600 dark:text-violet-300 text-xs font-semibold hover:underline">
                                            View →
                                        </Link>
                                    }
                                >
                                    <span className="inline-flex items-center gap-2"><Target className="size-4 text-violet-500" /> {o.title}</span>
                                </SoftCardTitle>
                                <SoftCardBody className="space-y-3">
                                    <div>
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-muted-foreground">Overall</span>
                                            <span className="font-bold">{o.progress}%</span>
                                        </div>
                                        <div className="bg-muted/40 ring-border/60 mt-1 h-2 overflow-hidden rounded-full ring-1">
                                            <div className="from-violet-500 to-indigo-600 h-full bg-gradient-to-r" style={{ width: `${o.progress}%` }} />
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Owner: {o.owner?.name ?? '—'}{o.team ? ` · Team: ${o.team.name}` : ''}{o.project ? ` · Project: ${o.project.title}` : ''}
                                    </p>
                                    <ul className="space-y-1.5">
                                        {o.key_results.slice(0, 5).map((k) => (
                                            <li key={k.id} className="flex items-center gap-2 text-xs">
                                                <span className={cn('inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold ring-1 capitalize', STATUS_TONE[k.status] ?? '')}>{k.status.replace('_', ' ')}</span>
                                                <span className="min-w-0 flex-1 truncate">{k.title}</span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {k.current_value}{k.unit ?? ''} / {k.target_value}{k.unit ?? ''}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </SoftCardBody>
                            </SoftCard>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
