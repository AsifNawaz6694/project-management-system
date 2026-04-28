import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Target, Trash2, TrendingUp } from 'lucide-react';
import { useState } from 'react';

interface UserMini { id: number; name: string; avatar?: string | null }
interface KrUpdate { id: number; value: string; confidence: string; note: string | null; recorded_at: string; recorder: UserMini | null }
interface KeyResult {
    id: number; title: string; description: string | null;
    metric_type: string; start_value: string | number; target_value: string | number; current_value: string | number; unit: string | null;
    status: string; owner: UserMini | null; progress: number; updates: KrUpdate[];
}

interface Props {
    objective: {
        id: number; title: string; description: string | null;
        period: string; starts_at: string; ends_at: string; status: string; visibility: string; progress: number;
        owner: UserMini | null;
        team: { id: number; name: string; color: string } | null;
        project: { id: number; slug: string; title: string } | null;
        parent: { id: number; title: string; period: string } | null;
        children: Array<{ id: number; title: string; progress: number; status: string }>;
        key_results: KeyResult[];
    };
    canEdit: boolean;
    canDelete: boolean;
}

const STATUS_TONE: Record<string, string> = {
    on_track: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 ring-emerald-200',
    at_risk: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 ring-amber-200',
    off_track: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 ring-rose-200',
    achieved: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300 ring-violet-200',
};

export default function OkrShow({ objective, canEdit, canDelete }: Props) {
    const [updatingId, setUpdatingId] = useState<number | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'OKRs', href: '/okrs' },
        { title: objective.title, href: route('okrs.show', objective.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={objective.title} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link href={route('okrs.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium">
                    <ArrowLeft className="size-3.5" /> Back to OKRs
                </Link>
                <PageHeader
                    eyebrow={`${objective.period} · ${objective.status}`}
                    title={objective.title}
                    description={objective.description ?? undefined}
                    actions={
                        <>
                            {canEdit && <Button asChild variant="secondary" size="sm" className="gap-1.5"><Link href={route('okrs.edit', objective.id)}><Pencil className="size-3.5" /> Edit</Link></Button>}
                            {canDelete && <Button variant="outline" size="sm" onClick={() => setConfirmDelete(true)} className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5"><Trash2 className="size-3.5" /> Delete</Button>}
                        </>
                    }
                />

                <SoftCard>
                    <SoftCardTitle eyebrow="Progress" action={<span className="text-2xl font-extrabold">{objective.progress}%</span>}>
                        <span className="inline-flex items-center gap-2"><Target className="size-4 text-violet-500" /> Overall</span>
                    </SoftCardTitle>
                    <SoftCardBody>
                        <div className="bg-muted/40 ring-border/60 h-3 overflow-hidden rounded-full ring-1">
                            <div className="from-violet-500 to-indigo-600 h-full bg-gradient-to-r transition-all" style={{ width: `${objective.progress}%` }} />
                        </div>
                        <p className="text-muted-foreground mt-2 text-xs">
                            {objective.starts_at} → {objective.ends_at}{objective.owner ? ` · Owner: ${objective.owner.name}` : ''}{objective.team ? ` · ${objective.team.name}` : ''}{objective.project ? ` · ${objective.project.title}` : ''}
                            {objective.parent ? ` · Aligns to: ${objective.parent.title}` : ''}
                        </p>
                    </SoftCardBody>
                </SoftCard>

                <div className="space-y-3">
                    {objective.key_results.map((k) => (
                        <SoftCard key={k.id}>
                            <SoftCardTitle
                                eyebrow={k.status.replace('_', ' ')}
                                action={
                                    <button onClick={() => setUpdatingId(updatingId === k.id ? null : k.id)} className="text-violet-600 dark:text-violet-300 inline-flex items-center gap-1 text-xs font-semibold hover:underline">
                                        <TrendingUp className="size-3.5" /> Record progress
                                    </button>
                                }
                            >
                                {k.title}
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-3">
                                {k.description && <p className="text-muted-foreground text-xs">{k.description}</p>}
                                <div>
                                    <div className="flex items-baseline justify-between text-xs">
                                        <span className={cn('inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold ring-1 capitalize', STATUS_TONE[k.status] ?? '')}>{k.status.replace('_', ' ')}</span>
                                        <span className="font-bold tabular-nums">
                                            {k.current_value}{k.unit ?? ''} / {k.target_value}{k.unit ?? ''} ({k.progress}%)
                                        </span>
                                    </div>
                                    <div className="bg-muted/40 ring-border/60 mt-1 h-2 overflow-hidden rounded-full ring-1">
                                        <div className="from-violet-500 to-indigo-600 h-full bg-gradient-to-r" style={{ width: `${k.progress}%` }} />
                                    </div>
                                </div>

                                {updatingId === k.id && <KrUpdateForm objectiveId={objective.id} keyResultId={k.id} onDone={() => setUpdatingId(null)} />}

                                {k.updates.length > 0 && (
                                    <details className="text-xs">
                                        <summary className="text-muted-foreground hover:text-foreground cursor-pointer font-semibold">Update history ({k.updates.length})</summary>
                                        <ul className="mt-2 space-y-1.5">
                                            {k.updates.map((u) => (
                                                <li key={u.id} className="bg-muted/30 ring-border/60 ring-1 rounded-lg px-2.5 py-1.5">
                                                    <p>{u.recorder?.name ?? '—'} · {new Date(u.recorded_at).toLocaleString()} · <span className="font-semibold">{u.value}</span> · {u.confidence}</p>
                                                    {u.note && <p className="text-muted-foreground mt-0.5">{u.note}</p>}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    ))}
                </div>

                {objective.children.length > 0 && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Aligned">Child objectives</SoftCardTitle>
                        <SoftCardBody>
                            <ul className="space-y-2">
                                {objective.children.map((c) => (
                                    <li key={c.id}>
                                        <Link href={route('okrs.show', c.id)} className="bg-muted/30 ring-border/60 ring-1 hover:bg-muted/60 flex items-center gap-3 rounded-xl p-3 transition-colors">
                                            <span className="from-violet-500 to-indigo-600 flex size-8 items-center justify-center rounded-lg bg-gradient-to-br text-white"><Target className="size-4" /></span>
                                            <p className="min-w-0 flex-1 truncate text-sm font-semibold">{c.title}</p>
                                            <span className="text-xs font-bold">{c.progress}%</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>
                )}
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete "${objective.title}"?`}
                description="This permanently removes the objective, its key results, and all progress history."
                confirmLabel="Delete"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => router.delete(route('okrs.destroy', objective.id))}
            />
        </AppLayout>
    );
}

function KrUpdateForm({ objectiveId, keyResultId, onDone }: { objectiveId: number; keyResultId: number; onDone: () => void }) {
    const form = useForm({ value: '', confidence: 'on_track', note: '', recorded_at: new Date().toISOString().slice(0, 16) });
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('okrs.key-results.updates', { objective: objectiveId, keyResult: keyResultId }), {
            preserveScroll: true,
            onSuccess: () => { form.reset(); onDone(); },
        });
    };
    return (
        <form onSubmit={submit} className="bg-muted/30 ring-border/60 ring-1 grid gap-2 rounded-xl p-3 sm:grid-cols-[1fr_140px_140px_auto]">
            <Input type="number" step="0.01" value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} placeholder="New value" required />
            <Select value={form.data.confidence} onValueChange={(v) => form.setData('confidence', v)}>
                <SelectTrigger className="h-10 rounded-lg"><SelectValue /></SelectTrigger>
                <SelectContent className="rounded-xl">
                    <SelectItem value="on_track">On track</SelectItem>
                    <SelectItem value="at_risk">At risk</SelectItem>
                    <SelectItem value="off_track">Off track</SelectItem>
                    <SelectItem value="achieved">Achieved</SelectItem>
                </SelectContent>
            </Select>
            <Input type="datetime-local" value={form.data.recorded_at} onChange={(e) => form.setData('recorded_at', e.target.value)} />
            <Button type="submit" size="sm" disabled={form.processing}>Save</Button>
            <Input className="sm:col-span-4" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Optional note" />
        </form>
    );
}
