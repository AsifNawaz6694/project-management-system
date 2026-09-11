import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';

interface Person {
    id: number;
    name: string;
    initials: string;
}
type QuestionRow = {
    body: string;
    kind: string;
    required: boolean;
};
type PairRow = {
    subject_user_id: number | '';
    reviewer_id: number | '';
    relationship: string;
};

interface Props {
    people: Person[];
    kinds: string[];
    statuses: string[];
    question_kinds: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Feedback', href: '/feedback' },
    { title: 'New cycle', href: '/feedback/cycles/create' },
];

export default function FeedbackCreate({ people, kinds, statuses, question_kinds }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const twoWeeks = new Date();
    twoWeeks.setDate(twoWeeks.getDate() + 14);

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        kind: string;
        description: string;
        starts_at: string;
        ends_at: string;
        status: string;
        anonymous: boolean;
        questions: QuestionRow[];
        pairs: PairRow[];
    }>({
        name: '',
        kind: 'peer',
        description: '',
        starts_at: today,
        ends_at: twoWeeks.toISOString().slice(0, 10),
        status: 'draft',
        anonymous: false,
        questions: [
            { body: 'What did this person do well?', kind: 'text', required: true },
            { body: 'What could they improve?', kind: 'text', required: true },
        ],
        pairs: [],
    });

    const addQuestion = () => setData('questions', [...data.questions, { body: '', kind: 'text', required: true }]);
    const removeQuestion = (i: number) =>
        setData(
            'questions',
            data.questions.filter((_, idx) => idx !== i),
        );
    const setQuestion = (i: number, p: Partial<QuestionRow>) =>
        setData(
            'questions',
            data.questions.map((q, idx) => (idx === i ? { ...q, ...p } : q)),
        );

    const addPair = () => setData('pairs', [...data.pairs, { subject_user_id: '', reviewer_id: '', relationship: '' }]);
    const removePair = (i: number) =>
        setData(
            'pairs',
            data.pairs.filter((_, idx) => idx !== i),
        );
    const setPair = (i: number, p: Partial<PairRow>) =>
        setData(
            'pairs',
            data.pairs.map((x, idx) => (idx === i ? { ...x, ...p } : x)),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('feedback.cycles.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New feedback cycle" />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Feedback"
                    title="New feedback cycle"
                    description="Define questions, pair reviewers with subjects, then activate to notify everyone."
                />

                <SoftCard>
                    <SoftCardTitle eyebrow="Cycle">Basics</SoftCardTitle>
                    <SoftCardBody className="grid gap-4 md:grid-cols-2">
                        <Field label="Name" error={errors.name} className="md:col-span-2">
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="H1 360 — Engineering" />
                        </Field>
                        <Field label="Kind" error={errors.kind}>
                            <Select value={data.kind} onValueChange={(v) => setData('kind', v)}>
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {kinds.map((k) => (
                                        <SelectItem key={k} value={k}>
                                            {k}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Status" error={errors.status}>
                            <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Starts" error={errors.starts_at}>
                            <Input type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                        </Field>
                        <Field label="Ends" error={errors.ends_at}>
                            <Input type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} required />
                        </Field>
                        <Field label="Anonymous" error={errors.anonymous} className="md:col-span-2">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.anonymous}
                                    onChange={(e) => setData('anonymous', e.target.checked)}
                                    className="size-4 rounded"
                                />
                                Hide reviewer identity from the subject when displaying responses
                            </label>
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="bg-card ring-border rounded-xl px-3.5 py-2.5 text-sm ring-1"
                            />
                        </Field>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Questions"
                        action={
                            <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={addQuestion}>
                                <Plus className="size-3.5" /> Add
                            </Button>
                        }
                    >
                        What you ask reviewers
                    </SoftCardTitle>
                    <SoftCardBody>
                        <ul className="space-y-2.5">
                            {data.questions.map((q, i) => (
                                <li
                                    key={i}
                                    className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_140px_auto_auto]"
                                >
                                    <Input
                                        value={q.body}
                                        onChange={(e) => setQuestion(i, { body: e.target.value })}
                                        required
                                        placeholder={`Question ${i + 1}`}
                                    />
                                    <Select value={q.kind} onValueChange={(v) => setQuestion(i, { kind: v })}>
                                        <SelectTrigger className="h-10 rounded-lg">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {question_kinds.map((k) => (
                                                <SelectItem key={k} value={k}>
                                                    {k}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <label className="flex items-center gap-1.5 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={q.required}
                                            onChange={(e) => setQuestion(i, { required: e.target.checked })}
                                            className="size-3.5"
                                        />
                                        required
                                    </label>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeQuestion(i)}
                                        className="text-rose-600 dark:text-rose-400"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Reviewers"
                        action={
                            <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={addPair}>
                                <Plus className="size-3.5" /> Add pair
                            </Button>
                        }
                    >
                        Who reviews whom
                    </SoftCardTitle>
                    <SoftCardBody>
                        {data.pairs.length === 0 ? (
                            <p className="text-muted-foreground text-xs">Add at least one (subject, reviewer) pair, then activate the cycle.</p>
                        ) : (
                            <ul className="space-y-2">
                                {data.pairs.map((p, i) => (
                                    <li
                                        key={i}
                                        className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_1fr_140px_auto]"
                                    >
                                        <Select
                                            value={p.subject_user_id ? String(p.subject_user_id) : 'none'}
                                            onValueChange={(v) => setPair(i, { subject_user_id: v === 'none' ? '' : Number(v) })}
                                        >
                                            <SelectTrigger className="h-10 rounded-lg">
                                                <SelectValue placeholder="Subject (reviewed)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">— Subject —</SelectItem>
                                                {people.map((person) => (
                                                    <SelectItem key={person.id} value={String(person.id)}>
                                                        {person.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            value={p.reviewer_id ? String(p.reviewer_id) : 'none'}
                                            onValueChange={(v) => setPair(i, { reviewer_id: v === 'none' ? '' : Number(v) })}
                                        >
                                            <SelectTrigger className="h-10 rounded-lg">
                                                <SelectValue placeholder="Reviewer (gives feedback)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">— Reviewer —</SelectItem>
                                                {people.map((person) => (
                                                    <SelectItem key={person.id} value={String(person.id)}>
                                                        {person.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            value={p.relationship}
                                            onChange={(e) => setPair(i, { relationship: e.target.value })}
                                            placeholder="peer, manager…"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removePair(i)}
                                            className="text-rose-600 dark:text-rose-400"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={route('feedback.cycles.index')}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <LoaderCircle className="size-4 animate-spin" />} Save cycle
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children, className }: { label: string; error?: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
