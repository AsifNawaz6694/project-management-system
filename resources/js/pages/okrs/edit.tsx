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

type KrRow = {
    id?: number;
    title: string;
    metric_type: string;
    start_value: number;
    target_value: number;
    current_value: number;
    unit: string;
    owner_id: number | null;
};

interface Props {
    objective: {
        id: number;
        title: string;
        description: string | null;
        period: string;
        starts_at: string;
        ends_at: string;
        status: string;
        visibility: string;
        owner_id: number | null;
        parent_id: number | null;
        team_id: number | null;
        project_id: number | null;
        keyResults: Array<{
            id: number;
            title: string;
            metric_type: string;
            start_value: string;
            target_value: string;
            current_value: string;
            unit: string | null;
            owner_id: number | null;
        }>;
    };
    people: Array<{ id: number; name: string; initials: string }>;
    teams: Array<{ id: number; name: string }>;
    projects: Array<{ id: number; slug: string; title: string }>;
    parents: Array<{ id: number; title: string; period: string }>;
    statuses: string[];
    visibilities: string[];
    metric_types: string[];
}

/**
 * A type alias, not an interface: only an alias picks up the implicit index
 * signature that Inertia's FormDataType requires.
 */
type ObjectiveFormState = {
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

export default function OkrEdit({ objective, people, teams, projects, parents, statuses, visibilities, metric_types }: Props) {
    const { data, setData, patch, processing, errors } = useForm<ObjectiveFormState>({
        title: objective.title,
        description: objective.description ?? '',
        period: objective.period,
        starts_at: objective.starts_at,
        ends_at: objective.ends_at,
        status: objective.status,
        visibility: objective.visibility,
        owner_id: objective.owner_id,
        parent_id: objective.parent_id,
        team_id: objective.team_id,
        project_id: objective.project_id,
        key_results: (objective.keyResults ?? []).map((k) => ({
            title: k.title,
            metric_type: k.metric_type,
            start_value: Number(k.start_value),
            target_value: Number(k.target_value),
            current_value: Number(k.current_value),
            unit: k.unit ?? '',
            owner_id: k.owner_id,
        })) as KrRow[],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'OKRs', href: '/okrs' },
        { title: objective.title, href: route('okrs.show', objective.id) },
        { title: 'Edit', href: route('okrs.edit', objective.id) },
    ];

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
        patch(route('okrs.update', objective.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${objective.title}`} />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="OKRs"
                    title={`Edit ${objective.title}`}
                    description="Editing replaces the key results — record progress on the show page if you only want to update values."
                />

                <SoftCard>
                    <SoftCardTitle eyebrow="Objective">Details</SoftCardTitle>
                    <SoftCardBody className="grid gap-4 md:grid-cols-2">
                        <Field label="Title" error={errors.title} className="md:col-span-2">
                            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="bg-card ring-border rounded-xl px-3.5 py-2.5 text-sm ring-1"
                            />
                        </Field>
                        <Field label="Period" error={errors.period}>
                            <Input value={data.period} onChange={(e) => setData('period', e.target.value)} required />
                        </Field>
                        <Field label="Visibility" error={errors.visibility}>
                            <Select value={data.visibility} onValueChange={(v) => setData('visibility', v)}>
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                                <SelectContent>
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
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Parent" error={errors.parent_id}>
                            <Select
                                value={data.parent_id ? String(data.parent_id) : 'none'}
                                onValueChange={(v) => setData('parent_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent>
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
                                <SelectContent>
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
                                <SelectContent>
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
                        Replace key results
                    </SoftCardTitle>
                    <SoftCardBody>
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
                                            <SelectContent>
                                                {metric_types.map((m) => (
                                                    <SelectItem key={m} value={m}>
                                                        {m}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input value={k.unit} onChange={(e) => setKr(i, { unit: e.target.value })} placeholder="Unit" />
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
                    </SoftCardBody>
                </SoftCard>

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={route('okrs.show', objective.id)}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <LoaderCircle className="size-4 animate-spin" />} Save changes
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
