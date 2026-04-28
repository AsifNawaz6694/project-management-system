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
import { useEffect } from 'react';

interface Person { id: number; name: string; initials: string; avatar?: string | null; job_title?: string | null }
interface Project { id: number; slug: string; title: string }
interface Template {
    id: number;
    name: string;
    kind: string;
    description: string | null;
    agenda_items: Array<{ title: string; description?: string | null; time_allocation_minutes?: number | null }> | null;
}

interface Props {
    people: Person[];
    projects: Project[];
    templates: Template[];
    kinds: string[];
    recurrence: string[];
}

interface AgendaRow { title: string; description: string; time_allocation_minutes: number | null; presenter_id: number | null }
interface ParticipantRow { user_id: number; role: string }

interface FormState {
    title: string;
    kind: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string;
    project_id: number | null;
    template_id: number | null;
    recurrence_rule: string;
    recurrence_until: string;
    participants: ParticipantRow[];
    agenda_items: AgendaRow[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Meetings', href: '/meetings' },
    { title: 'New', href: '/meetings/create' },
];

export default function MeetingsCreate({ people, projects, templates, kinds, recurrence }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormState>({
        title: '',
        kind: 'team',
        description: '',
        location: '',
        starts_at: new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16),
        ends_at: '',
        project_id: null,
        template_id: null,
        recurrence_rule: 'none',
        recurrence_until: '',
        participants: [],
        agenda_items: [],
    });

    useEffect(() => {
        if (!data.template_id) return;
        const t = templates.find((x) => x.id === data.template_id);
        if (!t) return;
        setData((prev) => ({
            ...prev,
            kind: t.kind,
            description: t.description ?? prev.description,
            agenda_items: (t.agenda_items ?? []).map((a) => ({
                title: a.title,
                description: a.description ?? '',
                time_allocation_minutes: a.time_allocation_minutes ?? null,
                presenter_id: null,
            })),
        }));
    }, [data.template_id]);

    const addAgenda = () =>
        setData('agenda_items', [...data.agenda_items, { title: '', description: '', time_allocation_minutes: null, presenter_id: null }]);
    const removeAgenda = (i: number) =>
        setData('agenda_items', data.agenda_items.filter((_, idx) => idx !== i));
    const setAgenda = (i: number, p: Partial<AgendaRow>) =>
        setData('agenda_items', data.agenda_items.map((a, idx) => (idx === i ? { ...a, ...p } : a)));

    const togglePerson = (userId: number) => {
        const exists = data.participants.find((p) => p.user_id === userId);
        if (exists) {
            setData('participants', data.participants.filter((p) => p.user_id !== userId));
        } else {
            setData('participants', [...data.participants, { user_id: userId, role: 'attendee' }]);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('meetings.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New meeting" />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader eyebrow="Meeting" title="Schedule a meeting" description="Pick a template or build the agenda from scratch. Add attendees and recurrence as needed." />

                <SoftCard>
                    <SoftCardTitle eyebrow="Basics">Meeting details</SoftCardTitle>
                    <SoftCardBody className="grid gap-4 md:grid-cols-2">
                        <Field label="Template" error={errors.template_id} className="md:col-span-2">
                            <Select value={data.template_id ? String(data.template_id) : 'none'} onValueChange={(v) => setData('template_id', v === 'none' ? null : Number(v))}>
                                <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="None — build from scratch" /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">None</SelectItem>
                                    {templates.map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.name} ({t.kind.replace('_', ' ')})</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Title" error={errors.title} className="md:col-span-2">
                            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus placeholder="Quarterly planning, 1:1, sprint review…" />
                        </Field>
                        <Field label="Kind" error={errors.kind}>
                            <Select value={data.kind} onValueChange={(v) => setData('kind', v)}>
                                <SelectTrigger className="h-11 rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {kinds.map((k) => <SelectItem key={k} value={k}>{k.replace('_', ' ')}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Project (optional)" error={errors.project_id}>
                            <Select value={data.project_id ? String(data.project_id) : 'none'} onValueChange={(v) => setData('project_id', v === 'none' ? null : Number(v))}>
                                <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="—" /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">—</SelectItem>
                                    {projects.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.title}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Starts at" error={errors.starts_at}>
                            <Input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                        </Field>
                        <Field label="Ends at" error={errors.ends_at}>
                            <Input type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                        </Field>
                        <Field label="Location / link" error={errors.location} className="md:col-span-2">
                            <Input value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder="Meeting room, Zoom link…" />
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="bg-card shadow-soft-xs ring-border focus-visible:border-foreground/30 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 hover:border-foreground/20 ring-1 w-full rounded-xl px-3.5 py-2.5 text-sm transition-all focus-visible:outline-none focus-visible:ring-4"
                            />
                        </Field>
                        <Field label="Recurrence" error={errors.recurrence_rule}>
                            <Select value={data.recurrence_rule} onValueChange={(v) => setData('recurrence_rule', v)}>
                                <SelectTrigger className="h-11 rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {recurrence.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </Field>
                        {data.recurrence_rule !== 'none' && (
                            <Field label="Recur until" error={errors.recurrence_until}>
                                <Input type="date" value={data.recurrence_until} onChange={(e) => setData('recurrence_until', e.target.value)} />
                            </Field>
                        )}
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle eyebrow="People">Attendees</SoftCardTitle>
                    <SoftCardBody>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {people.map((p) => {
                                const selected = !!data.participants.find((x) => x.user_id === p.id);
                                return (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => togglePerson(p.id)}
                                        className={cn(
                                            'ring-border/60 hover:bg-muted/30 flex items-center gap-3 rounded-xl p-2.5 ring-1 transition-all text-left',
                                            selected && 'ring-violet-500 bg-violet-50 dark:bg-violet-500/10 ring-2',
                                        )}
                                    >
                                        <span className="from-violet-500 to-indigo-600 flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white">{p.initials}</span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">{p.name}</p>
                                            {p.job_title && <p className="text-muted-foreground truncate text-[11px]">{p.job_title}</p>}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle eyebrow="Agenda" action={<Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={addAgenda}><Plus className="size-3.5" /> Add item</Button>}>Agenda items</SoftCardTitle>
                    <SoftCardBody>
                        {data.agenda_items.length === 0 ? (
                            <p className="text-muted-foreground text-xs">Add talking points to keep the meeting on track.</p>
                        ) : (
                            <ul className="space-y-2.5">
                                {data.agenda_items.map((a, i) => (
                                    <li key={i} className="bg-muted/30 ring-border/60 ring-1 grid gap-2 rounded-xl p-3 sm:grid-cols-[1fr_120px_auto]">
                                        <Input value={a.title} onChange={(e) => setAgenda(i, { title: e.target.value })} placeholder={`Topic ${i + 1}`} />
                                        <Input
                                            type="number"
                                            min={0}
                                            value={a.time_allocation_minutes ?? ''}
                                            onChange={(e) => setAgenda(i, { time_allocation_minutes: e.target.value ? Number(e.target.value) : null })}
                                            placeholder="Minutes"
                                        />
                                        <Button type="button" variant="ghost" size="sm" onClick={() => removeAgenda(i)} className="text-rose-600 dark:text-rose-400">
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                        <textarea
                                            value={a.description}
                                            onChange={(e) => setAgenda(i, { description: e.target.value })}
                                            rows={2}
                                            placeholder="Optional notes / context"
                                            className="bg-card ring-border/60 focus-visible:ring-violet-200/60 sm:col-span-3 ring-1 rounded-lg px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-4"
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost"><Link href={route('meetings.index')}>Cancel</Link></Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <LoaderCircle className="size-4 animate-spin" />} Schedule meeting
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children, className }: { label: string; error?: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
