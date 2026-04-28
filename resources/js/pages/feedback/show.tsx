import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, MessageSquareText, Play, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface UserMini { id: number; name: string; avatar?: string | null }
interface Question { id: number; body: string; kind: string; required: boolean; position: number }
interface Req { id: number; status: string; subject: UserMini; reviewer: UserMini; submitted_at: string | null; relationship: string | null }

interface Cycle {
    id: number; name: string; kind: string; description: string | null;
    starts_at: string; ends_at: string; status: string; anonymous: boolean;
    creator: UserMini | null;
    questions: Question[];
    requests: Req[];
}

interface Props { cycle: Cycle; canManage: boolean }

const STATUS_TONE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 ring-amber-200',
    submitted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 ring-emerald-200',
    declined: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 ring-rose-200',
};

export default function FeedbackShow({ cycle, canManage }: Props) {
    const getInitials = useInitials();
    const [confirmDelete, setConfirmDelete] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Feedback', href: '/feedback' },
        { title: cycle.name, href: route('feedback.cycles.show', cycle.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={cycle.name} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link href={route('feedback.cycles.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium">
                    <ArrowLeft className="size-3.5" /> Back to feedback
                </Link>
                <PageHeader
                    eyebrow={`${cycle.kind} · ${cycle.status}`}
                    title={cycle.name}
                    description={cycle.description ?? `${cycle.starts_at} → ${cycle.ends_at}${cycle.anonymous ? ' · anonymous' : ''}`}
                    actions={
                        canManage && (
                            <>
                                {cycle.status === 'draft' && (
                                    <Button onClick={() => router.post(route('feedback.cycles.activate', cycle.id))} className="gap-1.5"><Play className="size-3.5" /> Activate</Button>
                                )}
                                {cycle.status === 'active' && (
                                    <Button variant="secondary" onClick={() => router.post(route('feedback.cycles.close', cycle.id))} className="gap-1.5"><X className="size-3.5" /> Close cycle</Button>
                                )}
                                <Button variant="outline" size="sm" className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 className="size-3.5" /> Delete
                                </Button>
                            </>
                        )
                    }
                />

                <SoftCard>
                    <SoftCardTitle eyebrow="Questions" action={<span className="text-muted-foreground text-xs">{cycle.questions.length}</span>}>
                        <span className="inline-flex items-center gap-2"><MessageSquareText className="size-4 text-violet-500" /> What reviewers will answer</span>
                    </SoftCardTitle>
                    <SoftCardBody>
                        <ol className="space-y-2 text-sm">
                            {cycle.questions.map((q, i) => (
                                <li key={q.id} className="bg-muted/30 ring-border/60 ring-1 rounded-xl p-3">
                                    <p><span className="text-muted-foreground mr-1.5 text-xs font-semibold">{i + 1}.</span> {q.body}</p>
                                    <p className="text-muted-foreground text-[11px]">{q.kind}{q.required ? ' · required' : ' · optional'}</p>
                                </li>
                            ))}
                        </ol>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle eyebrow="Reviewers" action={<span className="text-muted-foreground text-xs">{cycle.requests.length}</span>}>Pairs</SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-2">
                            {cycle.requests.map((r) => (
                                <li key={r.id} className="bg-muted/30 ring-border/60 ring-1 flex items-center gap-3 rounded-xl p-3">
                                    <span className="from-violet-500 to-indigo-600 ring-card flex size-8 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white ring-2">{getInitials(r.reviewer.name)}</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold">{r.reviewer.name} <span className="text-muted-foreground text-[11px]">→</span> {r.subject.name}</p>
                                        <p className="text-muted-foreground text-[11px]">{r.relationship ?? '—'}{r.submitted_at ? ` · submitted ${new Date(r.submitted_at).toLocaleDateString()}` : ''}</p>
                                    </div>
                                    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 capitalize', STATUS_TONE[r.status] ?? '')}>
                                        {r.status === 'submitted' && <CheckCircle2 className="mr-1 size-3" />}
                                        {r.status}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </SoftCardBody>
                </SoftCard>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title="Delete this feedback cycle?"
                description="This permanently removes the cycle, its questions, requests, and any submitted responses."
                confirmLabel="Delete"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => router.delete(route('feedback.cycles.destroy', cycle.id))}
            />
        </AppLayout>
    );
}
