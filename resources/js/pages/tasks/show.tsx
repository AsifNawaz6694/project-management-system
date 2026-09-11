import { CommentThread, type CommentNode } from '@/components/comment-thread';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { RichText } from '@/components/rich-text';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatusReasonDialog, type PendingTransition } from '@/components/status-reason-dialog';
import { TaskLinksPanel, type TaskLinkGroups } from '@/components/task-links-panel';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import {
    TASK_PRIORITY_META,
    indexStatuses,
    isOverdue,
    relativeDue,
    statusChip,
    statusDot,
    type TaskLabel,
    type TaskPriority,
    type TaskStatus,
    type WorkflowStatus,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CalendarClock,
    CheckCircle2,
    Circle,
    Clock3,
    Download,
    Eye,
    EyeOff,
    FolderKanban,
    Paperclip,
    Pencil,
    Timer,
    Trash2,
    UploadCloud,
    User as UserIcon,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

interface UserMini {
    id: number;
    name: string;
    avatar?: string | null;
    job_title?: string | null;
}

interface TimeLogItem {
    id: number;
    minutes: number;
    started_at: string;
    note: string | null;
    user: UserMini | null;
}

interface TaskShowProps {
    task: {
        id: number;
        key_label?: string;
        number?: number | null;
        title: string;
        description: string | null;
        status: TaskStatus;
        priority: TaskPriority;
        due_date: string | null;
        estimate_minutes: number | null;
        logged_minutes: number;
        remaining_minutes?: number | null;
        start_date?: string | null;
        archived_at?: string | null;
        completed_at: string | null;
        created_at: string;
        project: { id: number; slug: string; key?: string; title: string; color: ProjectColor } | null;
        assignee: UserMini | null;
        creator: UserMini | null;
        team?: { id: number; name: string; color?: string } | null;
        type?: { id: number; key: string; name: string; color: string } | null;
        labels?: TaskLabel[];
        watchers?: UserMini[];
        subtasks: Array<{
            id: number;
            title: string;
            status: TaskStatus;
            completed_at: string | null;
            due_date: string | null;
            assignee_id: number | null;
        }>;
        attachments: Array<{
            id: number;
            file_name: string;
            file_size: number;
            mime_type: string | null;
            created_at: string;
            uploader: UserMini | null;
        }>;
        time_logs: TimeLogItem[];
    };
    activities: Array<{ id: number; description: string | null; action: string; created_at: string; user?: UserMini | null }>;
    comments: CommentNode[];
    statuses: WorkflowStatus[];
    allowedTransitions: string[];
    transitionOptions: Array<{ key: string; name: string; requires_comment: boolean; comment_label: string | null }>;
    statusHistory: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        note: string | null;
        duration_seconds: number | null;
        created_at: string;
        user?: UserMini | null;
    }>;
    links: TaskLinkGroups;
    linkTypes: Array<{ value: string; label: string }>;
    isBlocked: boolean;
    isWatching: boolean;
    can: {
        edit: boolean;
        status: boolean;
        delete: boolean;
        archive: boolean;
        logTime: boolean;
        link: boolean;
        comment: boolean;
        attach: boolean;
    };
}

export default function TaskShow({
    task,
    activities,
    comments,
    statuses,
    allowedTransitions,
    transitionOptions,
    statusHistory,
    links,
    linkTypes,
    isBlocked,
    isWatching,
    can,
}: TaskShowProps) {
    // Keep the previous local names so the rest of the template is untouched.
    const canEdit = can.edit;
    const canStatus = can.status;
    const canDelete = can.delete;
    const canLogTime = can.logTime;
    const { user } = usePermissions();
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [logForm, setLogForm] = useState({
        minutes: '',
        started_at: new Date().toISOString().slice(0, 16),
        note: '',
    });
    const [loggingTime, setLoggingTime] = useState(false);
    const [logError, setLogError] = useState<string | null>(null);
    const [confirmDeleteLogId, setConfirmDeleteLogId] = useState<number | null>(null);

    const submitTimeLog = (e: React.FormEvent) => {
        e.preventDefault();
        const minutes = parseHm(logForm.minutes);
        if (!minutes || minutes <= 0) {
            setLogError('Enter a duration like 30m, 1h, or 1h 15m.');
            return;
        }
        if (minutes > 1440) {
            setLogError('A single entry cannot exceed 24 hours. Split it.');
            return;
        }
        setLogError(null);
        setLoggingTime(true);
        router.post(
            route('tasks.time-logs.store', task.id),
            {
                minutes,
                started_at: new Date(logForm.started_at).toISOString(),
                note: logForm.note,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setLogForm({
                        minutes: '',
                        started_at: new Date().toISOString().slice(0, 16),
                        note: '',
                    }),
                onFinish: () => setLoggingTime(false),
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Tasks', href: '/tasks' },
        { title: task.title, href: route('tasks.show', task.id) },
    ];

    const statusIndex = useMemo(() => indexStatuses(statuses ?? []), [statuses]);
    const status = statusIndex[task.status];
    const priority = TASK_PRIORITY_META[task.priority];
    // Overdue now follows completion, not a hardcoded status string.
    const overdue = isOverdue(task.due_date, task.completed_at);

    const doneKey = useMemo(() => (statuses ?? []).find((s) => s.category === 'done')?.key ?? 'completed', [statuses]);
    const initialKey = useMemo(() => (statuses ?? []).find((s) => s.is_initial)?.key ?? (statuses ?? [])[0]?.key ?? 'todo', [statuses]);

    const [pendingStatus, setPendingStatus] = useState<PendingTransition | null>(null);
    const [statusSubmitting, setStatusSubmitting] = useState(false);
    const [statusError, setStatusError] = useState<string | null>(null);

    const submitStatus = (next: TaskStatus, reason?: string) => {
        setStatusSubmitting(true);
        router.patch(
            route('tasks.status', task.id),
            { status: next, ...(reason ? { reason } : {}) },
            {
                preserveScroll: true,
                onError: (errors) => setStatusError(errors.reason ?? errors.status ?? 'That move was rejected.'),
                onSuccess: () => {
                    setPendingStatus(null);
                    setStatusError(null);
                },
                onFinish: () => setStatusSubmitting(false),
            },
        );
    };

    const updateStatus = (next: TaskStatus) => {
        if (next === task.status) return;

        // Ask for the reason first when this transition demands one.
        const option = (transitionOptions ?? []).find((o) => o.key === next);

        if (option?.requires_comment) {
            setStatusError(null);
            setPendingStatus({
                taskId: task.id,
                status: next,
                statusName: option.name,
                label: option.comment_label ?? 'Add a reason for this change.',
            });

            return;
        }

        submitStatus(next);
    };

    const toggleSubtask = (subId: number) => {
        const sub = task.subtasks.find((s) => s.id === subId);
        if (!sub) return;
        const nextStatus: TaskStatus = sub.completed_at ? initialKey : doneKey;
        router.patch(route('tasks.status', subId), { status: nextStatus }, { preserveScroll: true });
    };

    const toggleWatch = () => router.post(route('tasks.watch', task.id), {}, { preserveScroll: true });

    const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        router.post(
            route('tasks.attachments.store', task.id),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    if (fileInputRef.current) fileInputRef.current.value = '';
                },
            },
        );
    };

    const completedSubs = task.subtasks.filter((s) => s.completed_at).length;
    const [confirmDeleteTask, setConfirmDeleteTask] = useState(false);
    const [confirmDeleteAttachmentId, setConfirmDeleteAttachmentId] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={task.title} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={route('tasks.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to tasks
                </Link>

                <PageHeader
                    eyebrow={
                        task.project
                            ? ((
                                  <Link
                                      href={route('projects.show', task.project.slug)}
                                      className="hover:text-foreground inline-flex items-center gap-1.5 transition-colors"
                                  >
                                      <span className={cn('size-1.5 rounded-full', COLOR_DOT[task.project.color] ?? 'bg-blue-500')} />
                                      {task.project.title}
                                  </Link>
                              ) as unknown as string)
                            : 'Task'
                    }
                    title={task.title}
                    description={task.description ?? undefined}
                    actions={
                        <>
                            {canEdit && (
                                <Button asChild variant="secondary" size="sm" className="gap-1.5">
                                    <Link href={route('tasks.edit', task.id)}>
                                        <Pencil className="size-3.5" /> Edit
                                    </Link>
                                </Button>
                            )}
                            {canDelete && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5 text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 dark:text-rose-400 dark:ring-rose-500/30"
                                    onClick={() => setConfirmDeleteTask(true)}
                                >
                                    <Trash2 className="size-3.5" /> Delete
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        <SoftCard>
                            <SoftCardTitle eyebrow="Status">Update status</SoftCardTitle>
                            <SoftCardBody>
                                {isBlocked && (
                                    <div className="mb-3 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-700 ring-1 ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30">
                                        <Ban className="mt-0.5 size-4 shrink-0" />
                                        <span>This task is blocked by an unfinished task. Resolve the blocker before completing it.</span>
                                    </div>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {(statuses ?? []).map((s) => {
                                        const active = task.status === s.key;
                                        // Only transitions the workflow permits are offered.
                                        const permitted = active || (allowedTransitions ?? []).includes(s.key);

                                        return (
                                            <button
                                                key={s.key}
                                                type="button"
                                                disabled={!canStatus || active || !permitted}
                                                title={!permitted && !active ? 'This transition is not allowed by the workflow' : undefined}
                                                onClick={() => updateStatus(s.key)}
                                                className={cn(
                                                    'inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold ring-1 transition-all ring-inset',
                                                    active
                                                        ? statusChip(s.color) + ' ring-2'
                                                        : 'bg-muted/40 ring-border/60 text-muted-foreground hover:text-foreground hover:ring-foreground/20',
                                                    (!canStatus || !permitted) && !active && 'cursor-not-allowed opacity-45',
                                                )}
                                            >
                                                <span className={cn('size-2 rounded-full', statusDot(s.color))} />
                                                {s.name}
                                                {/* A pencil hints that this move will ask for a reason. */}
                                                {!active && permitted && (transitionOptions ?? []).find((o) => o.key === s.key)?.requires_comment && (
                                                    <Pencil className="size-3 opacity-60" />
                                                )}
                                                {active && <CheckCircle2 className="size-4" />}
                                            </button>
                                        );
                                    })}
                                </div>
                                {!canStatus && (
                                    <p className="text-muted-foreground mt-3 text-[11px]">
                                        Only the assignee, the owning team or a manager can change this status.
                                    </p>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        {task.description && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Brief">Description</SoftCardTitle>
                                <SoftCardBody>
                                    <RichText value={task.description} />
                                </SoftCardBody>
                            </SoftCard>
                        )}

                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Breakdown"
                                action={
                                    task.subtasks.length > 0 && (
                                        <span className="text-muted-foreground text-xs font-semibold">
                                            {completedSubs} / {task.subtasks.length} done
                                        </span>
                                    )
                                }
                            >
                                Subtasks
                            </SoftCardTitle>
                            <SoftCardBody>
                                {task.subtasks.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No subtasks. Edit the task to add a breakdown.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {task.subtasks.map((s) => {
                                            const done = !!s.completed_at;
                                            return (
                                                <li
                                                    key={s.id}
                                                    className={cn(
                                                        'bg-muted/30 ring-border/60 flex items-center gap-3 rounded-xl p-3 ring-1 transition-all',
                                                        done && 'opacity-70',
                                                    )}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleSubtask(s.id)}
                                                        disabled={!canEdit && !canStatus}
                                                        className={cn(
                                                            'flex size-7 items-center justify-center rounded-lg transition-all',
                                                            done
                                                                ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
                                                                : 'bg-card ring-border text-muted-foreground hover:text-foreground ring-1',
                                                        )}
                                                    >
                                                        {done ? <CheckCircle2 className="size-4" /> : <Circle className="size-4" />}
                                                    </button>
                                                    <p className={cn('flex-1 text-sm font-medium', done && 'line-through')}>{s.title}</p>
                                                    {s.due_date && (
                                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-[11px]">
                                                            <CalendarClock className="size-3" /> {relativeDue(s.due_date)}
                                                        </span>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Files"
                                action={
                                    <>
                                        <input ref={fileInputRef} type="file" className="hidden" onChange={handleFile} />
                                        <Button
                                            type="button"
                                            variant="soft"
                                            size="sm"
                                            className="gap-1.5"
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={uploading}
                                        >
                                            <UploadCloud className="size-3.5" /> {uploading ? 'Uploading…' : 'Upload'}
                                        </Button>
                                    </>
                                }
                            >
                                Attachments
                            </SoftCardTitle>
                            <SoftCardBody>
                                {task.attachments.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">Nothing attached yet.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {task.attachments.map((a) => (
                                            <li key={a.id} className="bg-muted/30 ring-border/60 flex items-center gap-3 rounded-xl p-3 ring-1">
                                                <div className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                                                    <Paperclip className="size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold">{a.file_name}</p>
                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                        {(a.file_size / 1024).toFixed(1)} KB · {a.uploader?.name ?? '—'} ·{' '}
                                                        {new Date(a.created_at).toLocaleString()}
                                                    </p>
                                                </div>
                                                <a
                                                    href={route('tasks.attachments.download', { task: task.id, attachment: a.id })}
                                                    className="text-muted-foreground hover:text-foreground ring-border hover:ring-foreground/30 inline-flex size-8 items-center justify-center rounded-lg ring-1 transition-all"
                                                >
                                                    <Download className="size-3.5" />
                                                </a>
                                                {(a.uploader?.id === user?.id || canEdit) && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setConfirmDeleteAttachmentId(a.id)}
                                                        className="inline-flex size-8 items-center justify-center rounded-lg text-rose-600 transition-all hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Time"
                                action={
                                    <span className="text-muted-foreground text-xs font-semibold">
                                        {formatMinutes(task.logged_minutes)}
                                        {task.estimate_minutes ? ` / ${formatMinutes(task.estimate_minutes)}` : ''}
                                    </span>
                                }
                            >
                                Time tracking
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-4">
                                {task.estimate_minutes != null && task.estimate_minutes > 0 && (
                                    <div>
                                        <div className="bg-muted/40 ring-border/60 h-2 overflow-hidden rounded-full ring-1">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all',
                                                    task.logged_minutes <= task.estimate_minutes
                                                        ? 'bg-gradient-to-r from-blue-500 to-blue-700'
                                                        : 'bg-gradient-to-r from-rose-500 to-orange-600',
                                                )}
                                                style={{
                                                    width: `${Math.min(100, (task.logged_minutes / Math.max(1, task.estimate_minutes)) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                        {task.logged_minutes > task.estimate_minutes && (
                                            <p className="mt-2 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                                Over estimate by {formatMinutes(task.logged_minutes - task.estimate_minutes)}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {canLogTime ? (
                                    <form
                                        onSubmit={submitTimeLog}
                                        className="bg-muted/30 ring-border/60 grid gap-2 rounded-xl p-3 ring-1 sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        <input
                                            type="text"
                                            value={logForm.minutes}
                                            onChange={(e) => setLogForm({ ...logForm, minutes: e.target.value })}
                                            placeholder="Duration (e.g. 1h 30m)"
                                            className="bg-card ring-border/60 hover:ring-foreground/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none dark:focus-visible:ring-blue-500/20"
                                        />
                                        <input
                                            type="datetime-local"
                                            value={logForm.started_at}
                                            onChange={(e) => setLogForm({ ...logForm, started_at: e.target.value })}
                                            className="bg-card ring-border/60 hover:ring-foreground/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none dark:focus-visible:ring-blue-500/20"
                                        />
                                        <Button type="submit" size="sm" className="h-10 gap-1.5" disabled={loggingTime}>
                                            <Timer className="size-3.5" /> Log
                                        </Button>
                                        <input
                                            type="text"
                                            value={logForm.note}
                                            onChange={(e) => setLogForm({ ...logForm, note: e.target.value })}
                                            placeholder="What did you work on? (optional)"
                                            className="bg-card ring-border/60 hover:ring-foreground/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:ring-4 focus-visible:ring-blue-200/60 focus-visible:outline-none sm:col-span-3 dark:focus-visible:ring-blue-500/20"
                                        />
                                        {logError && (
                                            <p className="text-xs font-semibold text-rose-600 sm:col-span-3 dark:text-rose-400">{logError}</p>
                                        )}
                                    </form>
                                ) : (
                                    <p className="text-muted-foreground text-xs">You don't have permission to log time on this task.</p>
                                )}

                                {task.time_logs.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No time logged yet.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {task.time_logs.map((log) => {
                                            const canDeleteThis = log.user?.id === user?.id || canEdit;
                                            return (
                                                <li key={log.id} className="bg-muted/30 ring-border/60 flex items-start gap-3 rounded-xl p-3 ring-1">
                                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white">
                                                        <Clock3 className="size-4" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                            <p className="text-sm font-semibold">{formatMinutes(log.minutes)}</p>
                                                            <p className="text-muted-foreground text-[11px]">
                                                                {log.user?.name ?? '—'} · {new Date(log.started_at).toLocaleString()}
                                                            </p>
                                                        </div>
                                                        {log.note && <p className="text-muted-foreground mt-0.5 text-xs">{log.note}</p>}
                                                    </div>
                                                    {canDeleteThis && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setConfirmDeleteLogId(log.id)}
                                                            className="inline-flex size-7 items-center justify-center rounded-lg text-rose-600 transition-all hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Discussion">Comments & threads</SoftCardTitle>
                            <SoftCardBody>
                                <CommentThread
                                    comments={comments}
                                    storeUrl={route('tasks.comments.store', task.id)}
                                    destroyUrlFor={(id) => route('tasks.comments.destroy', { task: task.id, comment: id })}
                                    currentUserId={user?.id}
                                    canDeleteAny={canEdit}
                                    placeholder="Add a comment, use @name to mention…"
                                />
                            </SoftCardBody>
                        </SoftCard>
                    </div>

                    <aside className="space-y-4">
                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Relationships"
                                action={
                                    <Button size="sm" variant="ghost" onClick={toggleWatch} className="gap-1.5">
                                        {isWatching ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                                        {isWatching ? 'Unwatch' : 'Watch'}
                                    </Button>
                                }
                            >
                                Linked tasks
                            </SoftCardTitle>
                            <SoftCardBody>
                                <TaskLinksPanel taskId={task.id} links={links ?? {}} linkTypes={linkTypes ?? []} canLink={can.link} />

                                {(task.watchers?.length ?? 0) > 0 && (
                                    <div className="border-border/60 mt-4 border-t pt-3">
                                        <p className="text-muted-foreground mb-2 text-[10px] font-bold tracking-[0.12em] uppercase">
                                            Watchers ({task.watchers?.length})
                                        </p>
                                        <div className="flex flex-wrap gap-1">
                                            {task.watchers?.map((w) => (
                                                <span
                                                    key={w.id}
                                                    title={w.name}
                                                    className="ring-card flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[9px] font-bold text-white ring-2"
                                                >
                                                    {getInitials(w.name)}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Details">Overview</SoftCardTitle>
                            <SoftCardBody>
                                <dl className="space-y-3 text-sm">
                                    <Row label="Status">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                statusChip(status?.color),
                                            )}
                                        >
                                            <span className={cn('size-1.5 rounded-full', statusDot(status?.color))} />
                                            {status?.name ?? task.status}
                                        </span>
                                    </Row>
                                    <Row label="Priority">
                                        <span
                                            className={cn(
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                priority?.chip,
                                            )}
                                        >
                                            {priority?.label}
                                        </span>
                                    </Row>
                                    <Row label="Due">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1',
                                                overdue && 'font-semibold text-rose-600 dark:text-rose-400',
                                            )}
                                        >
                                            <CalendarClock className="size-3.5" /> {relativeDue(task.due_date)}
                                        </span>
                                    </Row>
                                    <Row label="Estimate">
                                        <span className="inline-flex items-center gap-1">
                                            <Timer className="size-3.5" />
                                            {task.estimate_minutes ? (
                                                formatMinutes(task.estimate_minutes)
                                            ) : (
                                                <span className="text-muted-foreground italic">none</span>
                                            )}
                                        </span>
                                    </Row>
                                    <Row label="Logged">
                                        <span className="inline-flex items-center gap-1">
                                            <Clock3 className="size-3.5" />
                                            {formatMinutes(task.logged_minutes)}
                                        </span>
                                    </Row>
                                    <Row label="Assignee">
                                        {task.assignee ? (
                                            <Link
                                                href={route('users.show', task.assignee.id)}
                                                className="inline-flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-300"
                                            >
                                                <span className="ring-card flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[10px] font-bold text-white ring-2">
                                                    {getInitials(task.assignee.name)}
                                                </span>
                                                <span className="text-sm font-medium">{task.assignee.name}</span>
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground italic">Unassigned</span>
                                        )}
                                    </Row>
                                    <Row label="Project">
                                        {task.project ? (
                                            <Link
                                                href={route('projects.show', task.project.slug)}
                                                className="inline-flex items-center gap-2 hover:text-blue-600 dark:hover:text-blue-300"
                                            >
                                                <FolderKanban className="size-3.5" />
                                                <span className="text-sm font-medium">{task.project.title}</span>
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </Row>
                                    <Row label="Created by">
                                        {task.creator ? (
                                            <span className="inline-flex items-center gap-1.5">
                                                <UserIcon className="size-3.5" />
                                                <span className="text-sm">{task.creator.name}</span>
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </Row>
                                    <Row label="Created">{new Date(task.created_at).toLocaleDateString()}</Row>
                                </dl>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Trail">Status history</SoftCardTitle>
                            <SoftCardBody>
                                {(statusHistory ?? []).length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No status changes yet.</p>
                                ) : (
                                    <ol className="space-y-2.5">
                                        {statusHistory.map((h) => {
                                            const to = statusIndex[h.to_status];
                                            const from = h.from_status ? statusIndex[h.from_status] : null;

                                            return (
                                                <li key={h.id} className="text-xs">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {from && (
                                                            <>
                                                                <span className="text-muted-foreground">{from.name}</span>
                                                                <span className="text-muted-foreground">→</span>
                                                            </>
                                                        )}
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset',
                                                                statusChip(to?.color),
                                                            )}
                                                        >
                                                            <span className={cn('size-1.5 rounded-full', statusDot(to?.color))} />
                                                            {to?.name ?? h.to_status}
                                                        </span>
                                                        {h.duration_seconds != null && h.duration_seconds > 0 && (
                                                            <span className="text-muted-foreground">after {formatDuration(h.duration_seconds)}</span>
                                                        )}
                                                    </div>

                                                    {/* The QA verdict, block reason or reopen justification. */}
                                                    {h.note && (
                                                        <p className="bg-muted/50 ring-border/60 mt-1 rounded-lg px-2.5 py-1.5 leading-relaxed whitespace-pre-line ring-1">
                                                            {h.note}
                                                        </p>
                                                    )}

                                                    <p className="text-muted-foreground mt-0.5 text-[10px]">
                                                        {h.user?.name ?? 'System'} · {new Date(h.created_at).toLocaleString()}
                                                    </p>
                                                </li>
                                            );
                                        })}
                                    </ol>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Timeline">Activity</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="before:bg-border relative space-y-4 pl-6 before:absolute before:top-1.5 before:bottom-1.5 before:left-2 before:w-px">
                                    {activities.length === 0 && <p className="text-muted-foreground text-xs">No activity yet.</p>}
                                    {activities.map((a, i) => (
                                        <li key={a.id} className="relative">
                                            <span
                                                className={cn(
                                                    'shadow-soft-xs ring-card absolute top-0.5 -left-6 size-3 rounded-full ring-2',
                                                    i % 4 === 0 && 'bg-gradient-to-br from-blue-500 to-blue-700',
                                                    i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                    i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                    i % 4 === 3 && 'bg-gradient-to-br from-slate-500 to-slate-700',
                                                )}
                                            />
                                            <p className="text-sm">{a.description ?? a.action}</p>
                                            <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                {a.user?.name && <>{a.user.name} · </>}
                                                {new Date(a.created_at).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </SoftCardBody>
                        </SoftCard>
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDeleteTask}
                onOpenChange={setConfirmDeleteTask}
                title={`Delete "${task.title}"?`}
                description="This permanently removes the task, its subtasks, comments, and attachments. This cannot be undone."
                confirmLabel="Delete task"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('tasks.destroy', task.id));
                    setConfirmDeleteTask(false);
                }}
            />
            <ConfirmDialog
                open={confirmDeleteAttachmentId !== null}
                onOpenChange={(o) => !o && setConfirmDeleteAttachmentId(null)}
                title="Remove this attachment?"
                description="The file will be deleted from storage. This cannot be undone."
                confirmLabel="Remove file"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteAttachmentId !== null) {
                        router.delete(route('tasks.attachments.destroy', { task: task.id, attachment: confirmDeleteAttachmentId }), {
                            preserveScroll: true,
                        });
                    }
                    setConfirmDeleteAttachmentId(null);
                }}
            />
            <ConfirmDialog
                open={confirmDeleteLogId !== null}
                onOpenChange={(o) => !o && setConfirmDeleteLogId(null)}
                title="Remove this time log?"
                description="The recorded time will be deducted from the task's totals. This cannot be undone."
                confirmLabel="Remove log"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteLogId !== null) {
                        router.delete(route('tasks.time-logs.destroy', { task: task.id, timeLog: confirmDeleteLogId }), { preserveScroll: true });
                    }
                    setConfirmDeleteLogId(null);
                }}
            />

            <StatusReasonDialog
                pending={pendingStatus}
                submitting={statusSubmitting}
                error={statusError}
                onCancel={() => setPendingStatus(null)}
                onConfirm={(reason) => {
                    if (pendingStatus) submitStatus(pendingStatus.status, reason);
                }}
            />
        </AppLayout>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">{label}</dt>
            <dd className="text-right">{children}</dd>
        </div>
    );
}

function formatMinutes(minutes: number): string {
    if (!minutes) return '0m';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h && m) return `${h}h ${m}m`;
    if (h) return `${h}h`;
    return `${m}m`;
}

function parseHm(input: string): number | null {
    if (!input.trim()) return null;
    const trimmed = input.trim().toLowerCase();
    const matches = [...trimmed.matchAll(/(\d+)\s*(h|m)/g)];
    if (matches.length > 0) {
        return matches.reduce((acc, [, n, unit]) => acc + Number(n) * (unit === 'h' ? 60 : 1), 0);
    }
    const asNumber = Number(trimmed);
    if (Number.isFinite(asNumber) && asNumber >= 0) {
        return Math.round(asNumber * 60);
    }
    return null;
}

function formatDuration(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = minutes / 60;
    if (hours < 24) return `${hours.toFixed(1)}h`;
    return `${(hours / 24).toFixed(1)}d`;
}
