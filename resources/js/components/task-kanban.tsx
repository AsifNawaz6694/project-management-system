import { StatusReasonDialog, type PendingTransition } from '@/components/status-reason-dialog';
import { TaskCard, type TaskCardData } from '@/components/task-card';
import { statusBar, statusDot, type WorkflowStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Ban, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

/**
 * Narrowest a stage column may become before the board scrolls instead.
 * Nine stages at this floor fit inside roughly 1000px, so any laptop shows the
 * whole pipeline in one row without scrolling.
 */
const MIN_COLUMN_REM = 6.75;

interface TaskKanbanProps {
    tasks: TaskCardData[];
    statuses: WorkflowStatus[];
    /** project id => status keys that project's workflow allows */
    projectStatuses?: Record<number, string[]>;
    /** project id => { "from>to" | "*>to": prompt } for moves needing a reason */
    reasonRules?: Record<number, Record<string, string>>;
    canCreate?: boolean;
    onCreateInColumn?: (status: string) => void;
    onMove?: (taskId: number, status: string, position: number) => void;
    canMove?: (task: TaskCardData) => boolean;
    selectedIds?: number[];
    onToggleSelect?: (id: number) => void;
}

export function TaskKanban({
    tasks,
    statuses,
    projectStatuses,
    reasonRules,
    canCreate,
    onCreateInColumn,
    onMove,
    canMove,
    selectedIds,
    onToggleSelect,
}: TaskKanbanProps) {
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const [overColumn, setOverColumn] = useState<string | null>(null);
    const [optimistic, setOptimistic] = useState<TaskCardData[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState<PendingTransition | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Columns come from the project's workflow rather than a hardcoded list.
    const columns = useMemo(() => [...statuses].sort((a, b) => a.position - b.position), [statuses]);

    const grouped = useMemo(() => {
        const source = optimistic ?? tasks;
        const acc: Record<string, TaskCardData[]> = {};

        for (const col of columns) acc[col.key] = [];

        for (const task of source) {
            // A task whose status is not in this workflow still needs a home.
            (acc[task.status] ??= []).push(task);
        }

        for (const key of Object.keys(acc)) {
            acc[key].sort((a, b) => a.position - b.position);
        }

        return acc;
    }, [tasks, optimistic, columns]);

    /**
     * The merged board can show a column that a given task's project does not
     * have. Checking here means an illegal move is refused with an explanation
     * rather than moving and then snapping back.
     */
    const allowsStatus = (task: TaskCardData, status: string): boolean => {
        const allowed = task.project ? projectStatuses?.[task.project.id] : undefined;
        return !allowed || allowed.includes(status);
    };

    /**
     * The prompt this move requires, if any. An explicit from>to rule wins over
     * the "from anywhere" wildcard, mirroring the server-side resolution.
     */
    const reasonFor = (task: TaskCardData, target: string): string | null => {
        const rules = task.project ? reasonRules?.[task.project.id] : undefined;
        if (!rules) return null;
        return rules[`${task.status}>${target}`] ?? rules[`*>${target}`] ?? null;
    };

    const commit = (task: TaskCardData, targetStatus: string, reason?: string) => {
        const targetCount = (optimistic ?? tasks).filter((t) => t.status === targetStatus).length;
        const newPosition = task.status === targetStatus ? task.position : targetCount + 1;

        const next = (optimistic ?? tasks).map((t) => (t.id === task.id ? { ...t, status: targetStatus, position: newPosition } : t));
        setOptimistic(next);

        if (onMove) {
            onMove(task.id, targetStatus, newPosition);
            return;
        }

        setSubmitting(true);
        router.patch(
            route('tasks.status', task.id),
            { status: targetStatus, position: newPosition, ...(reason ? { reason } : {}) },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    setOptimistic(null);
                    setError(errors.reason ?? errors.status ?? 'That move was rejected by the workflow.');
                },
                onSuccess: () => {
                    setOptimistic(null);
                    setError(null);
                    setPending(null);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const isColumnValidForDragged = (status: string): boolean => {
        if (draggedId === null) return true;
        const task = (optimistic ?? tasks).find((t) => t.id === draggedId);
        return task ? allowsStatus(task, status) : true;
    };

    const handleDrop = (e: React.DragEvent, targetStatus: string) => {
        e.preventDefault();
        setOverColumn(null);
        const id = Number(e.dataTransfer.getData('text/task-id'));
        if (!id) return;

        const source = (optimistic ?? tasks).find((t) => t.id === id);
        if (!source) return;

        setDraggedId(null);

        if (canMove && !canMove(source)) return;

        if (!allowsStatus(source, targetStatus)) {
            const column = columns.find((c) => c.key === targetStatus);
            setError(
                `“${column?.name ?? targetStatus}” is not part of ${source.project?.title ?? 'this project'}’s workflow, so this task cannot move there.`,
            );
            return;
        }

        setError(null);

        // Ask for the reason first when the workflow demands one — the card does
        // not move until it has been given.
        const prompt = reasonFor(source, targetStatus);

        if (prompt) {
            const column = columns.find((c) => c.key === targetStatus);
            setPending({
                taskId: source.id,
                status: targetStatus,
                statusName: column?.name ?? targetStatus,
                label: prompt,
            });

            return;
        }

        commit(source, targetStatus);
    };

    return (
        <div className="space-y-3">
            {error && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-700 ring-1 ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30"
                >
                    <Ban className="mt-0.5 size-4 shrink-0" />
                    <span className="flex-1">{error}</span>
                    <button type="button" onClick={() => setError(null)} className="font-semibold underline underline-offset-2">
                        Dismiss
                    </button>
                </div>
            )}

            {/* One row, always. Columns divide the available width equally, so
                every stage stays on screen and adapts as the viewport changes.
                The min-width floor keeps columns legible on a phone, where the
                strip scrolls rather than squeezing to nothing. */}
            <div className="scrollbar-soft -mx-1 overflow-x-auto px-1 pb-1">
                <div
                    className="grid gap-2 md:gap-2.5"
                    style={{
                        gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))`,
                        minWidth: `${columns.length * MIN_COLUMN_REM}rem`,
                    }}
                >
                    {columns.map((col) => {
                        const items = grouped[col.key] ?? [];
                        const legal = isColumnValidForDragged(col.key);

                        return (
                            <section
                                key={col.key}
                                onDragOver={(e) => {
                                    // Refuse the drop target outright when the column is
                                    // not part of the dragged task's workflow.
                                    if (!legal) return;
                                    e.preventDefault();
                                    setOverColumn(col.key);
                                }}
                                onDragLeave={() => setOverColumn((c) => (c === col.key ? null : c))}
                                onDrop={(e) => handleDrop(e, col.key)}
                                className={cn(
                                    'bg-muted/30 ring-border/60 flex min-w-0 flex-col rounded-xl p-2 ring-1 transition-colors sm:rounded-2xl sm:p-2.5',
                                    overColumn === col.key && 'ring-primary/50 bg-primary/5',
                                    draggedId !== null && !legal && 'opacity-40',
                                )}
                            >
                                <header className="mb-2 flex items-center gap-1.5 px-0.5">
                                    <span className={cn('size-2 shrink-0 rounded-full', statusDot(col.color))} />
                                    {/* Long stage names are truncated, with the full
                                    name available on hover. */}
                                    <h3 title={col.name} className="min-w-0 flex-1 truncate text-[10px] font-bold tracking-[0.08em] uppercase">
                                        {col.name}
                                    </h3>
                                    {draggedId !== null && !legal && <Ban className="text-muted-foreground size-3 shrink-0" />}
                                    <span className="text-muted-foreground bg-muted shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">
                                        {items.length}
                                    </span>
                                </header>

                                <div className={cn('mb-2 h-0.5 rounded-full bg-gradient-to-r', statusBar(col.color))} />

                                <div className="flex flex-1 flex-col gap-1.5">
                                    {items.map((task) => (
                                        <div
                                            key={task.id}
                                            draggable
                                            onDragStart={(e) => {
                                                e.dataTransfer.setData('text/task-id', String(task.id));
                                                setDraggedId(task.id);
                                            }}
                                            onDragEnd={() => setDraggedId(null)}
                                            className={cn('cursor-grab active:cursor-grabbing', draggedId === task.id && 'opacity-40')}
                                        >
                                            <TaskCard task={task} selected={selectedIds?.includes(task.id)} onToggleSelect={onToggleSelect} />
                                        </div>
                                    ))}

                                    {items.length === 0 && <p className="text-muted-foreground px-1 py-5 text-center text-[11px]">Nothing here.</p>}
                                </div>

                                {canCreate && onCreateInColumn && (
                                    <button
                                        type="button"
                                        onClick={() => onCreateInColumn(col.key)}
                                        className="text-muted-foreground hover:bg-muted hover:text-foreground mt-2 flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition-colors"
                                    >
                                        <Plus className="size-3.5" /> Add task
                                    </button>
                                )}
                            </section>
                        );
                    })}
                </div>
            </div>

            <StatusReasonDialog
                pending={pending}
                submitting={submitting}
                onCancel={() => setPending(null)}
                onConfirm={(reason) => {
                    const task = (optimistic ?? tasks).find((t) => t.id === pending?.taskId);
                    if (task && pending) commit(task, pending.status, reason);
                }}
            />
        </div>
    );
}
