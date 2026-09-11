import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { statusChip } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, ChevronDown, ChevronRight, Play, Plus, Square, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface SprintRow {
    id: number;
    name: string;
    goal: string | null;
    state: string;
    starts_at: string | null;
    ends_at: string | null;
    totals: { total_points: number; completed_points: number; total_tasks: number; completed_tasks: number };
}

interface TaskRow {
    id: number;
    key: string;
    title: string;
    status: string;
    priority: string;
    story_points: number | null;
    sprint_id: number | null;
    is_done: boolean;
    assignee: { id: number; name: string } | null;
    labels: Array<{ name: string; color: string }>;
}

interface Props {
    project: { id: number; slug: string; title: string; key: string };
    sprints: SprintRow[];
    tasks: TaskRow[];
    statuses: Array<{ key: string; name: string; color: string; category: string }>;
    can: { manage: boolean };
}

export default function Backlog({ project, sprints, tasks, statuses, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.title, href: `/projects/${project.slug}` },
        { title: 'Backlog', href: `/projects/${project.slug}/backlog` },
    ];

    const [selected, setSelected] = useState<number[]>([]);
    const [collapsed, setCollapsed] = useState<number[]>([]);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [completing, setCompleting] = useState<number | null>(null);

    const statusName = useMemo(() => Object.fromEntries(statuses.map((s) => [s.key, s.name])), [statuses]);

    const backlog = tasks.filter((t) => t.sprint_id === null);
    const forSprint = (id: number) => tasks.filter((t) => t.sprint_id === id);

    const toggle = (id: number) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const moveTo = (sprintId: number | null) => {
        if (selected.length === 0) return;
        router.post(route('sprints.assign', project.slug), { task_ids: selected, sprint_id: sprintId } as never, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    const estimate = (taskId: number, value: string) => {
        const points = value.trim() === '' ? null : Number(value);
        router.post(route('sprints.estimate', project.slug), { task_ids: [taskId], story_points: points } as never, { preserveScroll: true });
    };

    const createSprint = () => {
        if (!name.trim()) return;
        router.post(
            route('sprints.store', project.slug),
            { name: name.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setCreating(false);
                },
            },
        );
    };

    const TaskLine = ({ task }: { task: TaskRow }) => (
        <div
            className={cn(
                'border-border/60 flex flex-wrap items-center gap-2 border-b px-3 py-2 text-sm last:border-0',
                selected.includes(task.id) && 'bg-primary/5',
            )}
        >
            {can.manage && (
                <input
                    type="checkbox"
                    checked={selected.includes(task.id)}
                    onChange={() => toggle(task.id)}
                    aria-label={`Select ${task.key}`}
                    className="accent-primary size-4"
                />
            )}

            <Link href={route('tasks.show', task.id)} className="text-muted-foreground shrink-0 font-mono text-xs hover:underline">
                {task.key}
            </Link>

            <span className={cn('min-w-0 flex-1 truncate', task.is_done && 'text-muted-foreground line-through')}>{task.title}</span>

            {task.labels.map((label) => (
                <span key={label.name} className="bg-muted/60 rounded-full px-2 py-0.5 text-[11px]">
                    {label.name}
                </span>
            ))}

            <span className={cn('rounded-full px-2 py-0.5 text-[11px] font-medium', statusChip(task.status))}>
                {statusName[task.status] ?? task.status}
            </span>

            {task.assignee && <span className="text-muted-foreground hidden text-xs sm:inline">{task.assignee.name}</span>}

            <Input
                defaultValue={task.story_points ?? ''}
                onBlur={(e) => {
                    const next = e.target.value.trim() === '' ? null : Number(e.target.value);
                    if (next !== (task.story_points ?? null)) estimate(task.id, e.target.value);
                }}
                disabled={!can.manage}
                aria-label={`Story points for ${task.key}`}
                placeholder="–"
                className="h-7 w-12 shrink-0 px-1 text-center text-xs"
            />
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${project.title} — backlog`} />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow={project.key}
                    title="Backlog"
                    description="Plan the work, size it, and pull it into a sprint."
                    actions={
                        <div className="flex items-center gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={route('sprints.report', project.slug)}>
                                    <BarChart3 className="size-4" />
                                    Sprint report
                                </Link>
                            </Button>
                            {can.manage && !creating && (
                                <Button size="sm" onClick={() => setCreating(true)}>
                                    <Plus className="size-4" />
                                    New sprint
                                </Button>
                            )}
                        </div>
                    }
                />

                {creating && (
                    <SoftCard>
                        <SoftCardBody className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input
                                autoFocus
                                value={name}
                                placeholder="Sprint name, e.g. Sprint 4"
                                onChange={(e) => setName(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && createSprint()}
                                className="sm:max-w-sm"
                            />
                            <Button size="sm" onClick={createSprint} disabled={!name.trim()}>
                                Create
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setCreating(false)}>
                                Cancel
                            </Button>
                        </SoftCardBody>
                    </SoftCard>
                )}

                {selected.length > 0 && can.manage && (
                    <div className="bg-primary/5 ring-primary/20 sticky top-2 z-10 flex flex-wrap items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1">
                        <span className="font-medium">{selected.length} selected</span>
                        <span className="text-muted-foreground text-xs">Move to</span>
                        {sprints.map((sprint) => (
                            <Button key={sprint.id} size="sm" variant="outline" onClick={() => moveTo(sprint.id)}>
                                {sprint.name}
                            </Button>
                        ))}
                        <Button size="sm" variant="outline" onClick={() => moveTo(null)}>
                            Backlog
                        </Button>
                        <Button size="sm" variant="ghost" className="ml-auto" onClick={() => setSelected([])}>
                            Clear
                        </Button>
                    </div>
                )}

                {sprints.map((sprint) => {
                    const items = forSprint(sprint.id);
                    const isCollapsed = collapsed.includes(sprint.id);
                    const Chevron = isCollapsed ? ChevronRight : ChevronDown;

                    return (
                        <SoftCard key={sprint.id}>
                            <SoftCardBody className="flex flex-col gap-0 p-0">
                                <div className="border-border/60 flex flex-wrap items-center gap-2 border-b px-3 py-2.5">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setCollapsed((prev) => (isCollapsed ? prev.filter((x) => x !== sprint.id) : [...prev, sprint.id]))
                                        }
                                        aria-label={isCollapsed ? `Expand ${sprint.name}` : `Collapse ${sprint.name}`}
                                        className="hover:bg-muted rounded p-1"
                                    >
                                        <Chevron className="size-4" />
                                    </button>

                                    <span className="font-semibold">{sprint.name}</span>

                                    {sprint.state === 'active' && (
                                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                            Running
                                        </span>
                                    )}

                                    <span className="text-muted-foreground text-xs">
                                        {items.length} items · {sprint.totals.completed_points} / {sprint.totals.total_points} points
                                    </span>

                                    {sprint.goal && <span className="text-muted-foreground hidden truncate text-xs md:inline">— {sprint.goal}</span>}

                                    {can.manage && (
                                        <div className="ml-auto flex items-center gap-1">
                                            {sprint.state === 'future' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(route('sprints.start', [project.slug, sprint.id]), {}, { preserveScroll: true })
                                                    }
                                                >
                                                    <Play className="size-3.5" />
                                                    Start
                                                </Button>
                                            )}
                                            {sprint.state === 'active' && (
                                                <Button size="sm" variant="outline" onClick={() => setCompleting(sprint.id)}>
                                                    <Square className="size-3.5" />
                                                    Complete
                                                </Button>
                                            )}
                                            <button
                                                type="button"
                                                aria-label={`Delete ${sprint.name}`}
                                                onClick={() =>
                                                    router.delete(route('sprints.destroy', [project.slug, sprint.id]), { preserveScroll: true })
                                                }
                                                className="hover:bg-destructive/10 hover:text-destructive rounded-md p-1.5 transition-colors"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {completing === sprint.id && (
                                    <div className="bg-muted/30 flex flex-wrap items-center gap-2 px-3 py-2 text-sm">
                                        <span>Unfinished work goes to</span>
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    route('sprints.complete', [project.slug, sprint.id]),
                                                    { move_to: 'backlog' },
                                                    { preserveScroll: true, onSuccess: () => setCompleting(null) },
                                                )
                                            }
                                        >
                                            The backlog
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    route('sprints.complete', [project.slug, sprint.id]),
                                                    { move_to: 'next' },
                                                    { preserveScroll: true, onSuccess: () => setCompleting(null) },
                                                )
                                            }
                                        >
                                            The next sprint
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => setCompleting(null)}>
                                            Cancel
                                        </Button>
                                    </div>
                                )}

                                {!isCollapsed &&
                                    (items.length > 0 ? (
                                        items.map((task) => <TaskLine key={task.id} task={task} />)
                                    ) : (
                                        <p className="text-muted-foreground px-3 py-4 text-center text-xs">
                                            Nothing here yet — select work below and move it in.
                                        </p>
                                    ))}
                            </SoftCardBody>
                        </SoftCard>
                    );
                })}

                <SoftCard>
                    <SoftCardBody className="flex flex-col gap-0 p-0">
                        <div className="border-border/60 flex items-center gap-2 border-b px-3 py-2.5">
                            <span className="font-semibold">Backlog</span>
                            <span className="text-muted-foreground text-xs">{backlog.length} items</span>
                        </div>

                        {backlog.length > 0 ? (
                            backlog.map((task) => <TaskLine key={task.id} task={task} />)
                        ) : (
                            <p className="text-muted-foreground px-3 py-6 text-center text-xs">The backlog is empty.</p>
                        )}
                    </SoftCardBody>
                </SoftCard>
            </div>
        </AppLayout>
    );
}
