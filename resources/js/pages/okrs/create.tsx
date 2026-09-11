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
interface Team {
    id: number;
    name: string;
    color: string;
}
interface Project {
    id: number;
    slug: string;
    title: string;
}
interface Parent {
    id: number;
    title: string;
    period: string;
}

type KrRow = {
    title: string;
    metric_type: string;
    start_value: number;
    target_value: number;
    current_value: number;
    unit: string;
    owner_id: number | null;
};

type FormState = {
    title: string;
    description: string;
    period: string;
    starts_at: string;
    ends_at: string;
    status: string;
    visibility: string;
    owner_id: number | null;
    parent_id: number | null;
    team_id: number | null;
    project_id: number | null;
    key_results: KrRow[];
};

interface Props {
    people: Person[];
    teams: Team[];
    projects: Project[];
    parents: Parent[];
    statuses: string[];
    visibilities: string[];
    metric_types: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'OKRs', href: '/okrs' },
    { title: 'New', href: '/okrs/create' },
];

export default function OkrCreate({ people, teams, projects, parents, statuses, visibilities, metric_types }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const quarterEnd = new Date();
    quarterEnd.setMonth(quarterEnd.getMonth() + 3);
    const { data, setData, post, processing, errors } = useForm<FormState>({
        title: '',
        description: '',
        period: `Q${Math.floor(new Date().getMonth() / 3) + 1}-${new Date().getFullYear()}`,
        starts_at: today,
        ends_at: quarterEnd.toISOString().slice(0, 10),
        status: 'active',
        visibility: 'company',
        owner_id: null,
        parent_id: null,
        team_id: null,
        project_id: null,
        key_results: [{ title: '', metric_type: 'percentage', start_value: 0, target_value: 100, current_value: 0, unit: '%', owner_id: null }],
    });

    const addKr = () =>
        setData('key_results', [
            ...data.key_results,
            { title: '', metric_type: 'number', start_value: 0, target_value: 100, current_value: 0, unit: '', owner_id: null },
        ]);
    const removeKr = (i: number) =>
        setData(
            'key_results',
            data.key_results.filter((_, idx) => idx !== i),
        );
    const setKr = (i: number, p: Partial<KrRow>) =>
        setData(
            'key_results',
            data.key_results.map((k, idx) => (idx === i ? { ...k, ...p } : k)),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('okrs.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New objective" />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader eyebrow="OKRs" title="Create objective" description="State the qualitative outcome, then add measurable key results." />

                <SoftCard>
                    <SoftCardTitle eyebrow="Objective">Details</SoftCardTitle>
                    <SoftCardBody className="grid gap-4 md:grid-cols-2">
                        <Field label="Title" error={errors.title} className="md:col-span-2">
                            <Input
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                required
                                autoFocus
                                placeholder="What's the outcome you want?"
                            />
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="bg-card shadow-soft-xs ring-border w-full rounded-xl px-3.5 py-2.5 text-sm ring-1 focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none"
                            />
                        </Field>
                        <Field label="Period" error={errors.period}>
                            <Input value={data.period} onChange={(e) => setData('period', e.target.value)} placeholder="Q1-2026" required />
                        </Field>
                        <Field label="Visibility" error={errors.visibility}>
                            <Select value={data.visibility} onValueChange={(v) => setData('visibility', v)}>
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {visibilities.map((v) => (
                                        <SelectItem key={v} value={v}>
                                            {v}
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
                        <Field label="Owner" error={errors.owner_id}>
                            <Select
                                value={data.owner_id ? String(data.owner_id) : 'none'}
                                onValueChange={(v) => setData('owner_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">—</SelectItem>
                                    {people.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
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
                                <SelectContent className="rounded-xl">
                                    {statuses.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Parent objective" error={errors.parent_id}>
                            <Select
                                value={data.parent_id ? String(data.parent_id) : 'none'}
                                onValueChange={(v) => setData('parent_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">—</SelectItem>
                                    {parents.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.title} ({p.period})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Team" error={errors.team_id}>
                            <Select
                                value={data.team_id ? String(data.team_id) : 'none'}
                                onValueChange={(v) => setData('team_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">—</SelectItem>
                                    {teams.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Project" error={errors.project_id}>
                            <Select
                                value={data.project_id ? String(data.project_id) : 'none'}
                                onValueChange={(v) => setData('project_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">—</SelectItem>
                                    {projects.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Key results"
                        action={
                            <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={addKr}>
                                <Plus className="size-3.5" /> Add KR
                            </Button>
                        }
                    >
                        Measurable outcomes
                    </SoftCardTitle>
                    <SoftCardBody>
                        {data.key_results.length === 0 ? (
                            <p className="text-muted-foreground text-xs">Add at least one measurable key result.</p>
                        ) : (
                            <ul className="space-y-3">
                                {data.key_results.map((k, i) => (
                                    <li
                                        key={i}
                                        className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_auto] sm:items-start"
                                    >
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Input
                                                className="sm:col-span-2"
                                                value={k.title}
                                                onChange={(e) => setKr(i, { title: e.target.value })}
                                                placeholder={`Key result ${i + 1}`}
                                                required
                                            />
                                            <Select value={k.metric_type} onValueChange={(v) => setKr(i, { metric_type: v })}>
                                                <SelectTrigger className="h-10 rounded-lg">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent className="rounded-xl">
                                                    {metric_types.map((m) => (
                                                        <SelectItem key={m} value={m}>
                                                            {m}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                value={k.unit}
                                                onChange={(e) => setKr(i, { unit: e.target.value })}
                                                placeholder="Unit (e.g. %, $, count)"
                                            />
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={k.start_value}
                                                onChange={(e) => setKr(i, { start_value: Number(e.target.value) })}
                                                placeholder="Start"
                                            />
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={k.target_value}
                                                onChange={(e) => setKr(i, { target_value: Number(e.target.value) })}
                                                placeholder="Target"
                                                required
                                            />
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={k.current_value}
                                                onChange={(e) => setKr(i, { current_value: Number(e.target.value) })}
                                                placeholder="Current"
                                            />
                                            <Select
                                                value={k.owner_id ? String(k.owner_id) : 'none'}
                                                onValueChange={(v) => setKr(i, { owner_id: v === 'none' ? null : Number(v) })}
                                            >
                                                <SelectTrigger className="h-10 rounded-lg">
                                                    <SelectValue placeholder="KR Owner" />
                                                </SelectTrigger>
                                                <SelectContent className="rounded-xl">
                                                    <SelectItem value="none">— same as objective</SelectItem>
                                                    {people.map((p) => (
                                                        <SelectItem key={p.id} value={String(p.id)}>
                                                            {p.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeKr(i)}
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
                        <Link href={route('okrs.index')}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <LoaderCircle className="size-4 animate-spin" />} Create objective
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
