import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Inbox, MessageCircle, Plus } from 'lucide-react';

interface Cycle {
    id: number;
    name: string;
    kind: string;
    status: string;
    starts_at: string;
    ends_at: string;
    description: string | null;
    creator: { id: number; name: string } | null;
    questions_count: number;
    requests_count: number;
}

interface IncomingRow {
    id: number;
    status: string;
    subject: { id: number; name: string };
    cycle: { id: number; name: string; kind: string; ends_at: string };
}

interface Props { cycles: Cycle[]; incoming: IncomingRow[] }

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Feedback', href: '/feedback' },
];

const KIND_TONE: Record<string, string> = {
    peer: 'from-violet-500 to-indigo-600',
    '360': 'from-pink-500 to-fuchsia-600',
    manager: 'from-blue-500 to-cyan-600',
    self: 'from-amber-500 to-orange-600',
    team: 'from-emerald-500 to-teal-600',
};

export default function FeedbackIndex({ cycles, incoming }: Props) {
    const getInitials = useInitials();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Feedback" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="People"
                    title="Feedback"
                    description="Run 360 reviews, peer feedback, manager check-ins, and self-assessments."
                    actions={<Button asChild className="gap-2"><Link href={route('feedback.cycles.create')}><Plus className="size-4" /> New cycle</Link></Button>}
                />

                {incoming.length > 0 && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Inbox" action={<span className="text-muted-foreground text-xs">{incoming.length} pending</span>}>
                            <span className="inline-flex items-center gap-2"><Inbox className="size-4 text-violet-500" /> Awaiting your feedback</span>
                        </SoftCardTitle>
                        <SoftCardBody>
                            <ul className="space-y-2">
                                {incoming.map((r) => (
                                    <li key={r.id} className="bg-muted/30 ring-border/60 ring-1 flex items-center gap-3 rounded-xl p-3">
                                        <span className="from-pink-500 to-fuchsia-600 ring-card flex size-9 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white ring-2">
                                            {getInitials(r.subject.name)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">Share feedback on {r.subject.name}</p>
                                            <p className="text-muted-foreground truncate text-[11px]">"{r.cycle.name}" · {r.cycle.kind} · due {r.cycle.ends_at}</p>
                                        </div>
                                        <Button asChild size="sm">
                                            <Link href={route('feedback.requests.show', r.id)}>Respond</Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </SoftCardBody>
                    </SoftCard>
                )}

                <SoftCard>
                    <SoftCardTitle eyebrow="Cycles">All feedback cycles</SoftCardTitle>
                    <SoftCardBody>
                        {cycles.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No cycles yet.</p>
                        ) : (
                            <ul className="grid gap-3 md:grid-cols-2">
                                {cycles.map((c) => (
                                    <li key={c.id}>
                                        <Link href={route('feedback.cycles.show', c.id)} className="bg-muted/30 ring-border/60 ring-1 hover:ring-foreground/20 block rounded-xl p-4 transition-all">
                                            <div className="flex items-start gap-3">
                                                <span className={cn('shadow-soft-xs flex size-10 items-center justify-center rounded-xl bg-gradient-to-br text-white', KIND_TONE[c.kind] ?? 'from-violet-500 to-indigo-600')}>
                                                    <MessageCircle className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-bold">{c.name}</p>
                                                    <p className="text-muted-foreground text-[11px] capitalize">{c.kind} · {c.status} · {c.starts_at} → {c.ends_at}</p>
                                                    {c.description && <p className="text-muted-foreground mt-1 text-xs line-clamp-2">{c.description}</p>}
                                                    <p className="text-muted-foreground mt-1.5 text-[11px]">{c.questions_count} questions · {c.requests_count} requests</p>
                                                </div>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>
            </div>
        </AppLayout>
    );
}
