import { CommentThread, type CommentNode } from '@/components/comment-thread';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import {
    TASK_PRIORITY_META,
    TASK_STATUS_META,
    isOverdue,
    relativeDue,
    type TaskPriority,
    type TaskStatus,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    Circle,
    Clock3,
    Download,
    FolderKanban,
    Paperclip,
    Pencil,
    Timer,
    Trash2,
    UploadCloud,
    User as UserIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';

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
        title: string;
        description: string | null;
        status: TaskStatus;
        priority: TaskPriority;
        due_date: string | null;
        estimate_minutes: number | null;
        logged_minutes: number;
        completed_at: string | null;
        created_at: string;
        project: { id: number; slug: string; title: string; color: ProjectColor } | null;
        assignee: UserMini | null;
        creator: UserMini | null;
        subtasks: Array<{ id: number; title: string; status: TaskStatus; completed_at: string | null; due_date: string | null; assignee_id: number | null }>;
        attachments: Array<{ id: number; file_name: string; file_size: number; mime_type: string | null; created_at: string; uploader: UserMini | null }>;
        time_logs: TimeLogItem[];
    };
    activities: Array<{ id: number; description: string | null; action: string; created_at: string; user?: UserMini | null }>;
    comments: CommentNode[];
    canEdit: boolean;
    canStatus: boolean;
    canDelete: boolean;
    canLogTime: boolean;
}

export default function TaskShow({ task, activities, comments, canEdit, canStatus, canDelete, canLogTime }: TaskShowProps) {
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
                onSuccess: () => setLogForm({
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

    const status = TASK_STATUS_META[task.status];
    const priority = TASK_PRIORITY_META[task.priority];
    const overdue = isOverdue(task.due_date, task.status);

    const updateStatus = (next: TaskStatus) => {
        if (next === task.status) return;
        router.patch(route('tasks.status', task.id), { status: next }, { preserveScroll: true });
    };

    const toggleSubtask = (subId: number) => {
        const sub = task.subtasks.find((s) => s.id === subId);
        if (!sub) return;
        const nextStatus: TaskStatus = sub.completed_at ? 'todo' : 'completed';
        router.patch(route('tasks.status', subId), { status: nextStatus }, { preserveScroll: true });
    };

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
                <Link href={route('tasks.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors">
                    <ArrowLeft className="size-3.5" /> Back to tasks
                </Link>

                <PageHeader
                    eyebrow={
                        task.project ? (
                            <Link href={route('projects.show', task.project.slug)} className="hover:text-foreground inline-flex items-center gap-1.5 transition-colors">
                                <span className={cn('size-1.5 rounded-full', COLOR_DOT[task.project.color] ?? 'bg-violet-500')} />
                                {task.project.title}
                            </Link>
                        ) as unknown as string : 'Task'
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
                                    className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5"
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
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {(['todo', 'in_progress', 'completed'] as TaskStatus[]).map((s) => {
                                        const meta = TASK_STATUS_META[s];
                                        const active = task.status === s;
                                        return (
                                            <button
                                                key={s}
                                                type="button"
                                                disabled={!canStatus || active}
                                                onClick={() => updateStatus(s)}
                                                className={cn(
                                                    'group relative flex items-center justify-center gap-2 overflow-hidden rounded-xl px-4 py-3 text-sm font-semibold transition-all duration-300',
                                                    active
                                                        ? 'shadow-soft-md bg-gradient-to-br text-white ' + meta.column
                                                        : 'bg-muted/40 ring-border/60 ring-1 text-muted-foreground hover:text-foreground hover:ring-foreground/20',
                                                    !canStatus && 'cursor-not-allowed opacity-70',
                                                )}
                                            >
                                                <span className={cn('size-2 rounded-full', active ? 'bg-white/80' : meta.dot)} />
                                                {meta.label}
                                                {active && <CheckCircle2 className="size-4" />}
                                            </button>
                                        );
                                    })}
                                </div>
                                {!canStatus && (
                                    <p className="text-muted-foreground mt-3 text-[11px]">Only the assignee or a manager can change this task's status.</p>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        {task.description && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Brief">Description</SoftCardTitle>
                                <SoftCardBody>
                                    <p className="text-sm leading-relaxed whitespace-pre-line">{task.description}</p>
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
                                                <li key={s.id} className={cn('bg-muted/30 ring-border/60 ring-1 flex items-center gap-3 rounded-xl p-3 transition-all', done && 'opacity-70')}>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleSubtask(s.id)}
                                                        disabled={!canEdit && !canStatus}
                                                        className={cn(
                                                            'flex size-7 items-center justify-center rounded-lg transition-all',
                                                            done
                                                                ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
                                                                : 'bg-card ring-border ring-1 text-muted-foreground hover:text-foreground',
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
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            className="hidden"
                                            onChange={handleFile}
                                        />
                                        <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={() => fileInputRef.current?.click()} disabled={uploading}>
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
                                            <li key={a.id} className="bg-muted/30 ring-border/60 ring-1 flex items-center gap-3 rounded-xl p-3">
                                                <div className="from-blue-500 to-indigo-600 flex size-9 items-center justify-center rounded-lg bg-gradient-to-br text-white">
                                                    <Paperclip className="size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold">{a.file_name}</p>
                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                        {(a.file_size / 1024).toFixed(1)} KB · {a.uploader?.name ?? '—'} · {new Date(a.created_at).toLocaleString()}
                                                    </p>
                                                </div>
                                                <a
                                                    href={route('tasks.attachments.download', { task: task.id, attachment: a.id })}
                                                    className="text-muted-foreground hover:text-foreground inline-flex size-8 items-center justify-center rounded-lg ring-1 ring-border hover:ring-foreground/30 transition-all"
                                                >
                                                    <Download className="size-3.5" />
                                                </a>
                                                {(a.uploader?.id === user?.id || canEdit) && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setConfirmDeleteAttachmentId(a.id)}
                                                        className="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 inline-flex size-8 items-center justify-center rounded-lg transition-all"
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
                                        {formatMinutes(task.logged_minutes)}{task.estimate_minutes ? ` / ${formatMinutes(task.estimate_minutes)}` : ''}
                                    </span>
                                }
                            >
                                Time tracking
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-4">
                                {task.estimate_minutes != null && task.estimate_minutes > 0 && (
                                    <div>
                                        <div className="bg-muted/40 ring-border/60 ring-1 h-2 overflow-hidden rounded-full">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all',
                                                    task.logged_minutes <= task.estimate_minutes
                                                        ? 'bg-gradient-to-r from-violet-500 to-indigo-600'
                                                        : 'bg-gradient-to-r from-rose-500 to-orange-600',
                                                )}
                                                style={{ width: `${Math.min(100, (task.logged_minutes / Math.max(1, task.estimate_minutes)) * 100)}%` }}
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
                                    <form onSubmit={submitTimeLog} className="bg-muted/30 ring-border/60 ring-1 grid gap-2 rounded-xl p-3 sm:grid-cols-[1fr_1fr_auto]">
                                        <input
                                            type="text"
                                            value={logForm.minutes}
                                            onChange={(e) => setLogForm({ ...logForm, minutes: e.target.value })}
                                            placeholder="Duration (e.g. 1h 30m)"
                                            className="bg-card ring-border/60 hover:ring-foreground/20 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:outline-none focus-visible:ring-4"
                                        />
                                        <input
                                            type="datetime-local"
                                            value={logForm.started_at}
                                            onChange={(e) => setLogForm({ ...logForm, started_at: e.target.value })}
                                            className="bg-card ring-border/60 hover:ring-foreground/20 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:outline-none focus-visible:ring-4"
                                        />
                                        <Button type="submit" size="sm" className="h-10 gap-1.5" disabled={loggingTime}>
                                            <Timer className="size-3.5" /> Log
                                        </Button>
                                        <input
                                            type="text"
                                            value={logForm.note}
                                            onChange={(e) => setLogForm({ ...logForm, note: e.target.value })}
                                            placeholder="What did you work on? (optional)"
                                            className="bg-card ring-border/60 hover:ring-foreground/20 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 h-10 rounded-lg px-3 text-sm ring-1 transition-all focus-visible:outline-none focus-visible:ring-4 sm:col-span-3"
                                        />
                                        {logError && <p className="sm:col-span-3 text-xs font-semibold text-rose-600 dark:text-rose-400">{logError}</p>}
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
                                                <li key={log.id} className="bg-muted/30 ring-border/60 ring-1 flex items-start gap-3 rounded-xl p-3">
                                                    <div className="from-emerald-500 to-teal-600 flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-white">
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
                                                            className="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 inline-flex size-7 items-center justify-center rounded-lg transition-all"
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
                            <SoftCardTitle eyebrow="Details">Overview</SoftCardTitle>
                            <SoftCardBody>
                                <dl className="space-y-3 text-sm">
                                    <Row label="Status">
                                        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', status?.chip)}>
                                            <span className={cn('size-1.5 rounded-full', status?.dot)} />
                                            {status?.label}
                                        </span>
                                    </Row>
                                    <Row label="Priority">
                                        <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', priority?.chip)}>
                                            {priority?.label}
                                        </span>
                                    </Row>
                                    <Row label="Due">
                                        <span className={cn('inline-flex items-center gap-1', overdue && 'text-rose-600 dark:text-rose-400 font-semibold')}>
                                            <CalendarClock className="size-3.5" /> {relativeDue(task.due_date)}
                                        </span>
                                    </Row>
                                    <Row label="Estimate">
                                        <span className="inline-flex items-center gap-1">
                                            <Timer className="size-3.5" />
                                            {task.estimate_minutes ? formatMinutes(task.estimate_minutes) : <span className="text-muted-foreground italic">none</span>}
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
                                            <Link href={route('users.show', task.assignee.id)} className="inline-flex items-center gap-2 hover:text-violet-600 dark:hover:text-violet-300">
                                                <span className="from-violet-500 to-indigo-600 ring-card flex size-6 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white ring-2">
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
                                            <Link href={route('projects.show', task.project.slug)} className="inline-flex items-center gap-2 hover:text-violet-600 dark:hover:text-violet-300">
                                                <FolderKanban className="size-3.5" />
                                                <span className="text-sm font-medium">{task.project.title}</span>
                                            </Link>
                                        ) : '—'}
                                    </Row>
                                    <Row label="Created by">
                                        {task.creator ? (
                                            <span className="inline-flex items-center gap-1.5">
                                                <UserIcon className="size-3.5" />
                                                <span className="text-sm">{task.creator.name}</span>
                                            </span>
                                        ) : '—'}
                                    </Row>
                                    <Row label="Created">{new Date(task.created_at).toLocaleDateString()}</Row>
                                </dl>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Timeline">Activity</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                    {activities.length === 0 && <p className="text-muted-foreground text-xs">No activity yet.</p>}
                                    {activities.map((a, i) => (
                                        <li key={a.id} className="relative">
                                            <span
                                                className={cn(
                                                    'shadow-soft-xs ring-card absolute -left-6 top-0.5 size-3 rounded-full ring-2',
                                                    i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                                    i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                    i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                    i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
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
                        router.delete(route('tasks.attachments.destroy', { task: task.id, attachment: confirmDeleteAttachmentId }), { preserveScroll: true });
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
        </AppLayout>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</dt>
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
