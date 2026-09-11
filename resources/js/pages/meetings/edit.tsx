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
interface Project {
    id: number;
    slug: string;
    title: string;
}
type AgendaRow = {
    id?: number;
    title: string;
    description: string;
    time_allocation_minutes: number | null;
};

interface Meeting {
    id: number;
    title: string;
    kind: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string | null;
    status: string;
    project_id: number | null;
    participants: Array<{ id: number; pivot: { role: string } }>;
    agenda_items: Array<{ id: number; title: string; description: string | null; time_allocation_minutes: number | null }>;
}

interface Props {
    meeting: Meeting;
    people: Person[];
    projects: Project[];
    kinds: string[];
}

type ParticipantRow = {
    user_id: number;
    role: string;
};

/**
 * A type alias, not an interface: only an alias picks up the implicit index
 * signature that Inertia's FormDataType requires.
 */
type MeetingFormState = {
    title: string;
    kind: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string;
    status: string;
    project_id: number | null;
    participants: ParticipantRow[];
    agenda_items: AgendaRow[];
};

export default function MeetingEdit({ meeting, people, projects, kinds }: Props) {
    const { data, setData, patch, processing, errors } = useForm<MeetingFormState>({
        title: meeting.title,
        kind: meeting.kind,
        description: meeting.description ?? '',
        location: meeting.location ?? '',
        starts_at: meeting.starts_at.slice(0, 16),
        ends_at: meeting.ends_at ? meeting.ends_at.slice(0, 16) : '',
        status: meeting.status,
        project_id: meeting.project_id,
        participants: meeting.participants.map((p) => ({ user_id: p.id, role: p.pivot.role })),
        agenda_items: meeting.agenda_items.map((a) => ({
            title: a.title,
            description: a.description ?? '',
            time_allocation_minutes: a.time_allocation_minutes,
        })) as AgendaRow[],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Meetings', href: '/meetings' },
        { title: meeting.title, href: route('meetings.show', meeting.id) },
        { title: 'Edit', href: route('meetings.edit', meeting.id) },
    ];

    const togglePerson = (id: number) => {
        const exists = data.participants.find((p) => p.user_id === id);
        if (exists)
            setData(
                'participants',
                data.participants.filter((p) => p.user_id !== id),
            );
        else setData('participants', [...data.participants, { user_id: id, role: 'attendee' }]);
    };

    const addAgenda = () => setData('agenda_items', [...data.agenda_items, { title: '', description: '', time_allocation_minutes: null }]);
    const removeAgenda = (i: number) =>
        setData(
            'agenda_items',
            data.agenda_items.filter((_, idx) => idx !== i),
        );
    const setAgenda = (i: number, p: Partial<AgendaRow>) =>
        setData(
            'agenda_items',
            data.agenda_items.map((a, idx) => (idx === i ? { ...a, ...p } : a)),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('meetings.update', meeting.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${meeting.title}`} />
            <form onSubmit={submit} className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader eyebrow="Meeting" title={`Edit ${meeting.title}`} description="Update details, attendees, or the agenda." />

                <SoftCard>
                    <SoftCardTitle eyebrow="Basics">Details</SoftCardTitle>
                    <SoftCardBody className="grid gap-4 md:grid-cols-2">
                        <Field label="Title" error={errors.title} className="md:col-span-2">
                            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        </Field>
                        <Field label="Kind" error={errors.kind}>
                            <Select value={data.kind} onValueChange={(v) => setData('kind', v)}>
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {kinds.map((k) => (
                                        <SelectItem key={k} value={k}>
                                            {k.replace('_', ' ')}
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
                                    {['scheduled', 'in_progress', 'completed', 'cancelled'].map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s.replace('_', ' ')}
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
                        <Field label="Starts at" error={errors.starts_at}>
                            <Input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                        </Field>
                        <Field label="Ends at" error={errors.ends_at}>
                            <Input type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                        </Field>
                        <Field label="Location" error={errors.location} className="md:col-span-2">
                            <Input value={data.location} onChange={(e) => setData('location', e.target.value)} />
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="bg-card shadow-soft-xs ring-border w-full rounded-xl px-3.5 py-2.5 text-sm ring-1 focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none"
                            />
                        </Field>
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
                                            'ring-border/60 hover:bg-muted/30 flex items-center gap-3 rounded-xl p-2.5 text-left ring-1 transition-all',
                                            selected && 'bg-blue-50 ring-2 ring-blue-500 dark:bg-blue-500/10',
                                        )}
                                    >
                                        <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-xs font-bold text-white">
                                            {p.initials}
                                        </span>
                                        <p className="truncate text-sm font-semibold">{p.name}</p>
                                    </button>
                                );
                            })}
                        </div>
                    </SoftCardBody>
                </SoftCard>

                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Agenda"
                        action={
                            <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={addAgenda}>
                                <Plus className="size-3.5" /> Add
                            </Button>
                        }
                    >
                        Agenda
                    </SoftCardTitle>
                    <SoftCardBody>
                        {data.agenda_items.length === 0 ? (
                            <p className="text-muted-foreground text-xs">No agenda items.</p>
                        ) : (
                            <ul className="space-y-2.5">
                                {data.agenda_items.map((a, i) => (
                                    <li key={i} className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_120px_auto]">
                                        <Input
                                            value={a.title}
                                            onChange={(e) => setAgenda(i, { title: e.target.value })}
                                            placeholder={`Topic ${i + 1}`}
                                        />
                                        <Input
                                            type="number"
                                            value={a.time_allocation_minutes ?? ''}
                                            onChange={(e) =>
                                                setAgenda(i, { time_allocation_minutes: e.target.value ? Number(e.target.value) : null })
                                            }
                                            placeholder="Minutes"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeAgenda(i)}
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
                        <Link href={route('meetings.show', meeting.id)}>Cancel</Link>
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
