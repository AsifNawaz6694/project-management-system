import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Star, X } from 'lucide-react';

interface Question { id: number; body: string; kind: string; required: boolean }
interface Response { feedback_question_id: number; answer: string | null; rating: number | null }

interface Props {
    request: {
        id: number; status: string;
        cycle: { id: number; name: string; kind: string; description: string | null; questions: Question[] };
        subject: { id: number; name: string; avatar?: string | null; job_title?: string | null };
        responses: Response[];
    };
}

export default function FeedbackRespond({ request: req }: Props) {
    const initial: Record<number, { answer: string; rating: number | null }> = {};
    req.cycle.questions.forEach((q) => {
        const existing = req.responses.find((r) => r.feedback_question_id === q.id);
        initial[q.id] = { answer: existing?.answer ?? '', rating: existing?.rating ?? null };
    });

    const { data, setData, post, processing } = useForm<{ responses: Record<number, { answer: string; rating: number | null }> }>({
        responses: initial,
    });

    const setAnswer = (qid: number, p: Partial<{ answer: string; rating: number | null }>) =>
        setData('responses', { ...data.responses, [qid]: { ...data.responses[qid], ...p } });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('feedback.requests.submit', req.id));
    };

    const decline = () => {
        if (!confirm('Decline this feedback request? You won\'t be asked again for this subject in this cycle.')) return;
        router.post(route('feedback.requests.decline', req.id));
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Feedback', href: '/feedback' },
        { title: 'Respond', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Feedback for ${req.subject.name}`} />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <Link href={route('feedback.cycles.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium">
                    <ArrowLeft className="size-3.5" /> Back to feedback
                </Link>

                <PageHeader
                    eyebrow={req.cycle.kind}
                    title={`Feedback for ${req.subject.name}`}
                    description={req.cycle.description ?? `Cycle: ${req.cycle.name}`}
                />

                <div className="space-y-4">
                    {req.cycle.questions.map((q, i) => (
                        <SoftCard key={q.id}>
                            <SoftCardTitle eyebrow={`Question ${i + 1}${q.required ? ' · required' : ''}`}>{q.body}</SoftCardTitle>
                            <SoftCardBody>
                                {q.kind === 'rating' ? (
                                    <div className="flex items-center gap-1.5">
                                        {[1, 2, 3, 4, 5].map((n) => (
                                            <button
                                                key={n}
                                                type="button"
                                                onClick={() => setAnswer(q.id, { rating: n })}
                                                className={cn(
                                                    'flex size-10 items-center justify-center rounded-xl transition-all',
                                                    (data.responses[q.id].rating ?? 0) >= n ? 'shadow-soft-sm bg-gradient-to-br from-amber-400 to-orange-500 text-white' : 'bg-muted/40 ring-border ring-1 text-muted-foreground hover:text-foreground',
                                                )}
                                            >
                                                <Star className="size-5" />
                                            </button>
                                        ))}
                                    </div>
                                ) : q.kind === 'yes_no' ? (
                                    <div className="grid grid-cols-2 gap-2">
                                        {['yes', 'no'].map((v) => (
                                            <button
                                                key={v}
                                                type="button"
                                                onClick={() => setAnswer(q.id, { answer: v })}
                                                className={cn(
                                                    'rounded-xl px-4 py-2 text-sm font-semibold capitalize transition-all ring-1',
                                                    data.responses[q.id].answer === v ? 'bg-violet-500 text-white ring-violet-500' : 'bg-card text-muted-foreground hover:text-foreground ring-border',
                                                )}
                                            >
                                                {v}
                                            </button>
                                        ))}
                                    </div>
                                ) : (
                                    <textarea
                                        value={data.responses[q.id].answer}
                                        onChange={(e) => setAnswer(q.id, { answer: e.target.value })}
                                        rows={5}
                                        required={q.required}
                                        className="bg-card shadow-soft-xs ring-border focus-visible:ring-violet-200/60 ring-1 w-full rounded-xl px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-4"
                                    />
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    ))}
                </div>

                <div className="flex items-center justify-between gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={decline} className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5">
                        <X className="size-3.5" /> Decline
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        Submit feedback
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
