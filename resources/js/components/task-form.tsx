import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/rich-text';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TASK_PRIORITY_META, type TaskPriority, type TaskStatus, type WorkflowStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Circle, FileImage, LoaderCircle, Paperclip, Plus, Trash2, UploadCloud, X } from 'lucide-react';
import { type ChangeEvent, type DragEvent, type FormEventHandler, useRef, useState } from 'react';

export type TaskFormSubtask = {
    /** Present for existing rows; the server matches on it to update in place. */
    id?: number | null;
    title: string;
    description: string;
    due_date: string;
    completed: boolean;
    assignee_id: number | null;
};

export type TaskFormState = {
    project_id: number | null;
    title: string;
    description: string;
    status: TaskStatus;
    priority: TaskPriority;
    due_date: string;
    start_date: string;
    estimate_hm: string;
    assignee_id: number | null;
    team_id: number | null;
    task_type_id: number | null;
    labels: number[];
    subtasks: TaskFormSubtask[];
    attachments?: File[];
};

export function minutesToHm(minutes: number | null | undefined): string {
    if (!minutes || minutes <= 0) return '';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return [h ? `${h}h` : '', m ? `${m}m` : ''].filter(Boolean).join(' ');
}

export function hmToMinutes(input: string): number | null {
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

export interface ProjectOption {
    id: number;
    slug: string;
    title: string;
    color: string;
}

export interface AssigneeOption {
    id: number;
    name: string;
    initials: string;
    avatar?: string | null;
    job_title?: string | null;
    primary_role?: string | null;
}

export interface SimpleOption {
    id: number;
    name: string;
    color?: string;
}

interface TaskFormProps {
    initial: TaskFormState;
    projects: ProjectOption[];
    assignees: AssigneeOption[];
    statuses: WorkflowStatus[];
    types?: SimpleOption[];
    teams?: SimpleOption[];
    labelOptions?: SimpleOption[];
    priorities: string[];
    submitUrl: string;
    submitMethod: 'post' | 'patch';
    submitLabel: string;
    cancelUrl: string;
    enableAttachments?: boolean;
}

export function TaskForm({
    initial,
    projects,
    assignees,
    statuses,
    types = [],
    teams = [],
    labelOptions = [],
    priorities,
    submitUrl,
    submitMethod,
    submitLabel,
    cancelUrl,
    enableAttachments = false,
}: TaskFormProps) {
    const { data, setData, post, patch, processing, errors, transform } = useForm<TaskFormState>({
        ...initial,
        attachments: initial.attachments ?? [],
    });

    transform((current) => ({
        ...current,
        estimate_minutes: hmToMinutes(current.estimate_hm),
    }));
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = useState(false);

    const addFiles = (incoming: FileList | File[]) => {
        const next = [...(data.attachments ?? []), ...Array.from(incoming)].slice(0, 10);
        setData('attachments', next);
    };

    const removeAttachment = (index: number) => {
        setData(
            'attachments',
            (data.attachments ?? []).filter((_, i) => i !== index),
        );
    };

    const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) addFiles(e.target.files);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const onDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setDragActive(false);
        if (e.dataTransfer.files?.length) addFiles(e.dataTransfer.files);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const hasFiles = enableAttachments && (data.attachments ?? []).length > 0;
        const opts = hasFiles ? { preserveScroll: true, forceFormData: true } : { preserveScroll: true };
        const fn = submitMethod === 'post' ? post : patch;
        fn(submitUrl, opts);
    };

    const setSubtask = (i: number, patch: Partial<TaskFormSubtask>) => {
        setData(
            'subtasks',
            data.subtasks.map((s, idx) => (idx === i ? { ...s, ...patch } : s)),
        );
    };

    const addSubtask = () => {
        setData('subtasks', [...data.subtasks, { id: null, title: '', description: '', due_date: '', completed: false, assignee_id: null }]);
    };

    const removeSubtask = (i: number) => {
        setData(
            'subtasks',
            data.subtasks.filter((_, idx) => idx !== i),
        );
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <SoftCard>
                <SoftCardTitle eyebrow="Task">Basics</SoftCardTitle>
                <SoftCardBody className="grid gap-4 md:grid-cols-2">
                    <Field label="Project" error={errors.project_id} className="md:col-span-2">
                        <Select value={data.project_id ? String(data.project_id) : ''} onValueChange={(v) => setData('project_id', Number(v))}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue placeholder="Pick a project" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {projects.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Title" error={errors.title} className="md:col-span-2">
                        <Input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                            autoFocus
                            placeholder="What needs to happen?"
                        />
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <RichTextEditor
                            value={data.description}
                            onChange={(v) => setData('description', v)}
                            rows={4}
                            className="bg-card shadow-soft-xs"
                            placeholder="Add context, links, expected outcome…"
                        />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <Select value={data.status} onValueChange={(v) => setData('status', v as TaskStatus)}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {statuses.map((s) => (
                                    <SelectItem key={s.key} value={s.key}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Priority" error={errors.priority}>
                        <Select value={data.priority} onValueChange={(v) => setData('priority', v as TaskPriority)}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {priorities.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {TASK_PRIORITY_META[p as TaskPriority]?.label ?? p}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Start date" error={(errors as Record<string, string>).start_date}>
                        <Input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                    </Field>
                    <Field label="Due date" error={errors.due_date}>
                        <Input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                    </Field>
                    {types.length > 0 && (
                        <Field label="Type" error={(errors as Record<string, string>).task_type_id}>
                            <Select
                                value={data.task_type_id ? String(data.task_type_id) : 'none'}
                                onValueChange={(v) => setData('task_type_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="No type" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">No type</SelectItem>
                                    {types.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    )}
                    {teams.length > 0 && (
                        <Field label="Team" error={(errors as Record<string, string>).team_id} hint="Route this work to a team queue">
                            <Select
                                value={data.team_id ? String(data.team_id) : 'none'}
                                onValueChange={(v) => setData('team_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="No team" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">No team</SelectItem>
                                    {teams.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    )}
                    {labelOptions.length > 0 && (
                        <Field label="Labels" error={(errors as Record<string, string>).labels} className="md:col-span-2">
                            <div className="flex flex-wrap gap-1.5">
                                {labelOptions.map((l) => {
                                    const active = data.labels.includes(l.id);
                                    return (
                                        <button
                                            key={l.id}
                                            type="button"
                                            onClick={() => setData('labels', active ? data.labels.filter((x) => x !== l.id) : [...data.labels, l.id])}
                                            className={cn(
                                                'ring-border/60 rounded-full px-2.5 py-1 text-xs font-medium ring-1 transition-colors',
                                                active ? 'bg-primary text-primary-foreground ring-primary' : 'bg-card hover:bg-muted',
                                            )}
                                        >
                                            {l.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>
                    )}
                    <Field label="Estimate" error={(errors as Record<string, string>).estimate_minutes} hint="e.g. 1h 30m or 2.5">
                        <Input value={data.estimate_hm} onChange={(e) => setData('estimate_hm', e.target.value)} placeholder="2h" inputMode="text" />
                    </Field>
                    <Field label="Assignee" error={errors.assignee_id}>
                        <Select
                            value={data.assignee_id ? String(data.assignee_id) : 'none'}
                            onValueChange={(v) => setData('assignee_id', v === 'none' ? null : Number(v))}
                        >
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue placeholder="Unassigned" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="none">Unassigned</SelectItem>
                                {assignees.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>
                                        {a.name}
                                        {a.job_title ? ` · ${a.job_title}` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                </SoftCardBody>
            </SoftCard>

            <SoftCard>
                <SoftCardTitle
                    eyebrow="Breakdown"
                    action={
                        <Button type="button" variant="soft" size="sm" onClick={addSubtask} className="gap-1.5">
                            <Plus className="size-3.5" /> Add subtask
                        </Button>
                    }
                >
                    Subtasks
                </SoftCardTitle>
                <SoftCardBody>
                    {data.subtasks.length === 0 ? (
                        <p className="text-muted-foreground text-xs">Break the task into smaller checkable steps.</p>
                    ) : (
                        <ul className="space-y-2.5">
                            {data.subtasks.map((sub, i) => (
                                <li
                                    key={i}
                                    className="bg-muted/30 ring-border/60 grid items-center gap-2 rounded-xl p-3 ring-1 md:grid-cols-[auto_1fr_180px_180px_auto]"
                                >
                                    <button
                                        type="button"
                                        onClick={() => setSubtask(i, { completed: !sub.completed })}
                                        className={cn(
                                            'flex size-9 items-center justify-center rounded-lg transition-all',
                                            sub.completed
                                                ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
                                                : 'bg-card text-muted-foreground ring-border hover:text-foreground ring-1',
                                        )}
                                    >
                                        {sub.completed ? <CheckCircle2 className="size-4" /> : <Circle className="size-4" />}
                                    </button>
                                    <Input value={sub.title} onChange={(e) => setSubtask(i, { title: e.target.value })} placeholder="Subtask title" />
                                    <Select
                                        value={sub.assignee_id ? String(sub.assignee_id) : 'none'}
                                        onValueChange={(v) => setSubtask(i, { assignee_id: v === 'none' ? null : Number(v) })}
                                    >
                                        <SelectTrigger className="h-10 rounded-lg">
                                            <SelectValue placeholder="Unassigned" />
                                        </SelectTrigger>
                                        <SelectContent className="rounded-xl">
                                            <SelectItem value="none">Unassigned</SelectItem>
                                            {assignees.map((a) => (
                                                <SelectItem key={a.id} value={String(a.id)}>
                                                    {a.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input type="date" value={sub.due_date} onChange={(e) => setSubtask(i, { due_date: e.target.value })} />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                        onClick={() => removeSubtask(i)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </SoftCardBody>
            </SoftCard>

            {enableAttachments && (
                <SoftCard>
                    <SoftCardTitle
                        eyebrow="Files"
                        action={
                            <Button type="button" variant="soft" size="sm" onClick={() => fileInputRef.current?.click()} className="gap-1.5">
                                <Paperclip className="size-3.5" /> Attach
                            </Button>
                        }
                    >
                        Attachments &amp; screenshots
                    </SoftCardTitle>
                    <SoftCardBody>
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple
                            className="hidden"
                            onChange={onFileChange}
                            accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip"
                        />
                        <div
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDragActive(true);
                            }}
                            onDragLeave={() => setDragActive(false)}
                            onDrop={onDrop}
                            onClick={() => fileInputRef.current?.click()}
                            className={cn(
                                'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-8 text-center transition-all',
                                dragActive
                                    ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-500/10'
                                    : 'border-border/60 bg-muted/30 hover:border-foreground/20 hover:bg-muted/50',
                            )}
                        >
                            <div className="shadow-soft-sm flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 text-white">
                                <UploadCloud className="size-5" />
                            </div>
                            <p className="text-sm font-semibold">Drop files here or click to browse</p>
                            <p className="text-muted-foreground text-xs">Up to 10 files · 20 MB each · screenshots, PDFs, docs</p>
                        </div>
                        {(data.attachments ?? []).length > 0 && (
                            <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                                {(data.attachments ?? []).map((file, i) => {
                                    const isImage = file.type.startsWith('image/');
                                    const sizeKB = (file.size / 1024).toFixed(1);
                                    return (
                                        <li
                                            key={i}
                                            className="bg-card ring-border/60 group shadow-soft-xs flex items-center gap-3 rounded-xl p-2.5 ring-1"
                                        >
                                            <div
                                                className={cn(
                                                    'shadow-soft-xs flex size-10 items-center justify-center rounded-lg bg-gradient-to-br text-white',
                                                    isImage ? 'from-slate-500 to-slate-700' : 'from-blue-500 to-blue-700',
                                                )}
                                            >
                                                {isImage ? <FileImage className="size-4" /> : <Paperclip className="size-4" />}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-semibold">{file.name}</p>
                                                <p className="text-muted-foreground text-[10px]">
                                                    {sizeKB} KB · {file.type || 'unknown'}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    removeAttachment(i);
                                                }}
                                                className="text-muted-foreground inline-flex size-7 shrink-0 items-center justify-center rounded-lg transition-all hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        {(errors as Record<string, string>)['attachments'] && (
                            <p className="mt-2 text-xs text-rose-600">{(errors as Record<string, string>)['attachments']}</p>
                        )}
                    </SoftCardBody>
                </SoftCard>
            )}

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={cancelUrl}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing} className="gap-2">
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function Field({
    label,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">{label}</Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-[11px]">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
