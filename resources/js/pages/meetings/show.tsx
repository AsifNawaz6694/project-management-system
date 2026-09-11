import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRightCircle,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FilePen,
    ListChecks,
    MapPin,
    Pencil,
    Plus,
    Repeat,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface UserMini {
    id: number;
    name: string;
    avatar?: string | null;
    job_title?: string | null;
}
interface AgendaItem {
    id: number;
    title: string;
    description: string | null;
    time_allocation_minutes: number | null;
    status: string;
    presenter: UserMini | null;
}
interface ActionItem {
    id: number;
    title: string;
    description: string | null;
    status: string;
    due_date: string | null;
    assignee: UserMini | null;
    creator: UserMini | null;
    task: { id: number; title: string; status: string } | null;
}

interface Meeting {
    id: number;
    title: string;
    kind: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string | null;
    status: string;
    notes: string | null;
    summary: string | null;
    summary_generated_at: string | null;
    organizer: UserMini | null;
    project: { id: number; slug: string; title: string; color: string } | null;
    participants: Array<UserMini & { pivot: { role: string; rsvp_status: string } }>;
    agenda_items: AgendaItem[];
    action_items: ActionItem[];
    series_id: number | null;
}

interface Props {
    meeting: Meeting;
    series: Array<{ id: number; title: string; starts_at: string; status: string }>;
    projects: Array<{ id: number; slug: string; title: string }>;
    people: Array<{ id: number; name: string; initials: string }>;
    canEdit: boolean;
    canDelete: boolean;
}

export default function MeetingShow({ meeting, series, projects, people, canEdit, canDelete }: Props) {
    const { user } = usePermissions();
    const getInitials = useInitials();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [confirmDeleteAction, setConfirmDeleteAction] = useState<number | null>(null);
    const [convertingId, setConvertingId] = useState<number | null>(null);
    const [convertProject, setConvertProject] = useState<string>('');

    const notesForm = useForm({ notes: meeting.notes ?? '' });
    const newAction = useForm({ title: '', description: '', assignee_id: '', due_date: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Meetings', href: '/meetings' },
        { title: meeting.title, href: route('meetings.show', meeting.id) },
    ];

    const saveNotes = (e: React.FormEvent) => {
        e.preventDefault();
        notesForm.post(route('meetings.notes', meeting.id), { preserveScroll: true });
    };

    const submitNewAction = (e: React.FormEvent) => {
        e.preventDefault();
        newAction.post(route('meetings.action-items.store', meeting.id), {
            preserveScroll: true,
            onSuccess: () => newAction.reset(),
        });
    };

    const updateAction = (id: number, payload: Record<string, string | number | boolean | null>) =>
        router.patch(route('meetings.action-items.update', { meeting: meeting.id, actionItem: id }), payload, { preserveScroll: true });

    const setRsvp = (status: 'accepted' | 'declined' | 'tentative') =>
        router.post(route('meetings.rsvp', meeting.id), { rsvp_status: status }, { preserveScroll: true });

    const myRow = meeting.participants.find((p) => p.id === user?.id);
    const totalDuration = meeting.agenda_items.reduce((acc, a) => acc + (a.time_allocation_minutes ?? 0), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={meeting.title} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={route('meetings.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium"
                >
                    <ArrowLeft className="size-3.5" /> Back to meetings
                </Link>

                <PageHeader
                    eyebrow={meeting.kind.replace('_', ' ')}
                    title={meeting.title}
                    description={meeting.description ?? undefined}
                    actions={
                        <>
                            {canEdit && (
                                <Button asChild variant="secondary" size="sm" className="gap-1.5">
                                    <Link href={route('meetings.edit', meeting.id)}>
                                        <Pencil className="size-3.5" /> Edit
                                    </Link>
                                </Button>
                            )}
                            {canDelete && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setConfirmDelete(true)}
                                    className="gap-1.5 text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 dark:text-rose-400 dark:ring-rose-500/30"
                                >
                                    <Trash2 className="size-3.5" /> Cancel
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        {meeting.agenda_items.length > 0 && (
                            <SoftCard>
                                <SoftCardTitle
                                    eyebrow="Agenda"
                                    action={<span className="text-muted-foreground text-xs">{totalDuration ? `~${totalDuration} min` : ''}</span>}
                                >
                                    Talking points
                                </SoftCardTitle>
                                <SoftCardBody>
                                    <ol className="space-y-2.5">
                                        {meeting.agenda_items.map((a, i) => (
                                            <li key={a.id} className="bg-muted/30 ring-border/60 rounded-xl p-3 ring-1">
                                                <div className="flex items-baseline justify-between gap-3">
                                                    <p className="text-sm font-semibold">
                                                        <span className="text-muted-foreground mr-1.5 text-xs">{i + 1}.</span> {a.title}
                                                    </p>
                                                    {a.time_allocation_minutes != null && (
                                                        <span className="text-muted-foreground text-[11px]">{a.time_allocation_minutes}m</span>
                                                    )}
                                                </div>
                                                {a.description && <p className="text-muted-foreground mt-1 text-xs">{a.description}</p>}
                                            </li>
                                        ))}
                                    </ol>
                                </SoftCardBody>
                            </SoftCard>
                        )}

                        <SoftCard>
                            <SoftCardTitle eyebrow="Notes">
                                <span className="inline-flex items-center gap-1.5">
                                    <FilePen className="size-3.5" /> Meeting notes
                                </span>
                            </SoftCardTitle>
                            <SoftCardBody>
                                <form onSubmit={saveNotes} className="space-y-2">
                                    <textarea
                                        value={notesForm.data.notes}
                                        onChange={(e) => notesForm.setData('notes', e.target.value)}
                                        rows={8}
                                        className="bg-card shadow-soft-xs ring-border w-full rounded-xl px-3.5 py-2.5 text-sm ring-1 focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none"
                                        placeholder="Capture decisions, key points, follow-ups…"
                                    />
                                    <div className="flex justify-end">
                                        <Button type="submit" size="sm" disabled={notesForm.processing}>
                                            Save notes
                                        </Button>
                                    </div>
                                </form>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Action items">
                                <span className="inline-flex items-center gap-1.5">
                                    <ListChecks className="size-3.5" /> Action items
                                </span>
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-3">
                                <form
                                    onSubmit={submitNewAction}
                                    className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_180px_140px_auto]"
                                >
                                    <Input
                                        value={newAction.data.title}
                                        onChange={(e) => newAction.setData('title', e.target.value)}
                                        placeholder="Action item title"
                                        required
                                    />
                                    <Select
                                        value={newAction.data.assignee_id || 'none'}
                                        onValueChange={(v) => newAction.setData('assignee_id', v === 'none' ? '' : v)}
                                    >
                                        <SelectTrigger className="h-10 rounded-lg">
                                            <SelectValue placeholder="Assignee" />
                                        </SelectTrigger>
                                        <SelectContent className="rounded-xl">
                                            <SelectItem value="none">Unassigned</SelectItem>
                                            {people.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        type="date"
                                        value={newAction.data.due_date}
                                        onChange={(e) => newAction.setData('due_date', e.target.value)}
                                    />
                                    <Button type="submit" size="sm" className="gap-1.5" disabled={newAction.processing}>
                                        <Plus className="size-3.5" /> Add
                                    </Button>
                                </form>

                                {meeting.action_items.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No action items yet.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {meeting.action_items.map((a) => {
                                            const done = a.status === 'done';
                                            return (
                                                <li
                                                    key={a.id}
                                                    className={cn(
                                                        'bg-muted/30 ring-border/60 flex flex-wrap items-center gap-3 rounded-xl p-3 ring-1',
                                                        done && 'opacity-70',
                                                    )}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => updateAction(a.id, { status: done ? 'open' : 'done' })}
                                                        className={cn(
                                                            'flex size-7 items-center justify-center rounded-lg transition-all',
                                                            done
                                                                ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
                                                                : 'bg-card ring-border text-muted-foreground hover:text-foreground ring-1',
                                                        )}
                                                    >
                                                        <CheckCircle2 className="size-4" />
                                                    </button>
                                                    <div className="min-w-0 flex-1">
                                                        <p className={cn('text-sm font-semibold', done && 'line-through')}>{a.title}</p>
                                                        <p className="text-muted-foreground text-[11px]">
                                                            {a.assignee?.name ?? 'Unassigned'}
                                                            {a.due_date ? ` · due ${a.due_date}` : ''}
                                                            {a.task && (
                                                                <>
                                                                    {' '}
                                                                    ·{' '}
                                                                    <Link
                                                                        href={route('tasks.show', a.task.id)}
                                                                        className="text-blue-600 hover:underline dark:text-blue-300"
                                                                    >
                                                                        → task
                                                                    </Link>
                                                                </>
                                                            )}
                                                        </p>
                                                    </div>
                                                    {!a.task && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setConvertingId(a.id);
                                                                setConvertProject('');
                                                            }}
                                                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-300"
                                                        >
                                                            <ArrowRightCircle className="size-3.5" /> Convert to task
                                                        </button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => setConfirmDeleteAction(a.id)}
                                                        className="inline-flex size-7 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        {convertingId !== null && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Convert">Promote to task</SoftCardTitle>
                                <SoftCardBody className="space-y-3">
                                    <p className="text-muted-foreground text-xs">
                                        Pick a project to attach the new task to. The action item links back to the new task.
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                                        <Select value={convertProject || 'none'} onValueChange={setConvertProject}>
                                            <SelectTrigger className="h-10 rounded-lg">
                                                <SelectValue placeholder="Pick a project" />
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
                                        <Button
                                            size="sm"
                                            disabled={!convertProject || convertProject === 'none'}
                                            onClick={() => {
                                                router.post(
                                                    route('meetings.action-items.convert', { meeting: meeting.id, actionItem: convertingId }),
                                                    { project_id: Number(convertProject) },
                                                    { onFinish: () => setConvertingId(null) },
                                                );
                                            }}
                                        >
                                            Promote
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => setConvertingId(null)}>
                                            Cancel
                                        </Button>
                                    </div>
                                </SoftCardBody>
                            </SoftCard>
                        )}

                        {meeting.summary && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Summary">AI summary</SoftCardTitle>
                                <SoftCardBody>
                                    <p className="text-sm whitespace-pre-line">{meeting.summary}</p>
                                </SoftCardBody>
                            </SoftCard>
                        )}
                    </div>

                    <aside className="space-y-4">
                        <SoftCard>
                            <SoftCardTitle eyebrow="When">Schedule</SoftCardTitle>
                            <SoftCardBody className="space-y-2 text-sm">
                                <p className="inline-flex items-center gap-2">
                                    <CalendarClock className="text-muted-foreground size-4" /> {new Date(meeting.starts_at).toLocaleString()}
                                </p>
                                {meeting.ends_at && (
                                    <p className="text-muted-foreground text-xs">until {new Date(meeting.ends_at).toLocaleTimeString()}</p>
                                )}
                                {meeting.location && (
                                    <p className="inline-flex items-center gap-2 text-xs">
                                        <MapPin className="text-muted-foreground size-3.5" /> {meeting.location}
                                    </p>
                                )}
                                {meeting.series_id && (
                                    <div className="bg-muted/40 ring-border/60 mt-2 rounded-lg p-2 text-xs ring-1">
                                        <p className="inline-flex items-center gap-1 font-semibold">
                                            <Repeat className="size-3" /> Recurring series ({series.length} occurrences)
                                        </p>
                                        <ul className="mt-1.5 space-y-1">
                                            {series.slice(0, 6).map((s) => (
                                                <li key={s.id}>
                                                    <Link
                                                        href={route('meetings.show', s.id)}
                                                        className={cn(
                                                            'hover:text-foreground',
                                                            s.id === meeting.id ? 'text-foreground font-semibold' : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {new Date(s.starts_at).toLocaleString()}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        {myRow && myRow.pivot.role !== 'organizer' && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Your RSVP">Will you attend?</SoftCardTitle>
                                <SoftCardBody>
                                    <div className="grid grid-cols-3 gap-2">
                                        {(['accepted', 'tentative', 'declined'] as const).map((s) => (
                                            <button
                                                key={s}
                                                type="button"
                                                onClick={() => setRsvp(s)}
                                                className={cn(
                                                    'rounded-lg px-2 py-1.5 text-[11px] font-semibold capitalize ring-1 transition-all',
                                                    myRow.pivot.rsvp_status === s
                                                        ? 'bg-blue-500 text-white ring-blue-500'
                                                        : 'bg-card text-muted-foreground hover:text-foreground ring-border',
                                                )}
                                            >
                                                {s}
                                            </button>
                                        ))}
                                    </div>
                                </SoftCardBody>
                            </SoftCard>
                        )}

                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Attendees"
                                action={<span className="text-muted-foreground text-xs">{meeting.participants.length}</span>}
                            >
                                People
                            </SoftCardTitle>
                            <SoftCardBody>
                                <ul className="space-y-2">
                                    {meeting.participants.map((p) => (
                                        <li key={p.id} className="flex items-center gap-2.5">
                                            <span className="ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[10px] font-bold text-white ring-2">
                                                {getInitials(p.name)}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-semibold">{p.name}</p>
                                                <p className="text-muted-foreground truncate text-[10px]">
                                                    {p.pivot.role} · {p.pivot.rsvp_status}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </SoftCardBody>
                        </SoftCard>

                        {meeting.project && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Linked">Project</SoftCardTitle>
                                <SoftCardBody>
                                    <Link
                                        href={route('projects.show', meeting.project.slug)}
                                        className="inline-flex items-center gap-2 text-sm font-semibold hover:text-blue-600 dark:hover:text-blue-300"
                                    >
                                        <ClipboardList className="size-4" /> {meeting.project.title}
                                    </Link>
                                </SoftCardBody>
                            </SoftCard>
                        )}
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title="Cancel this meeting?"
                description="The meeting and its action items will be removed. Action items already converted to tasks will remain."
                confirmLabel="Cancel meeting"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => router.delete(route('meetings.destroy', meeting.id))}
            />
            <ConfirmDialog
                open={confirmDeleteAction !== null}
                onOpenChange={(o) => !o && setConfirmDeleteAction(null)}
                title="Remove this action item?"
                description="This cannot be undone. If it was already promoted to a task, the task remains untouched."
                confirmLabel="Remove"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteAction !== null) {
                        router.delete(route('meetings.action-items.destroy', { meeting: meeting.id, actionItem: confirmDeleteAction }), {
                            preserveScroll: true,
                        });
                    }
                    setConfirmDeleteAction(null);
                }}
            />
        </AppLayout>
    );
}
