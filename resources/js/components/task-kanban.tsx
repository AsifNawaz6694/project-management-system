import { TaskCard, type TaskCardData } from '@/components/task-card';
import { cn } from '@/lib/utils';
import { TASK_STATUS_META, type TaskStatus } from '@/lib/tasks';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

interface TaskKanbanProps {
    tasks: TaskCardData[];
    canCreate?: boolean;
    onCreateInColumn?: (status: TaskStatus) => void;
    onMove?: (taskId: number, status: TaskStatus, position: number) => void;
    canMove?: (task: TaskCardData) => boolean;
}

const COLUMNS: TaskStatus[] = ['todo', 'in_progress', 'completed'];

export function TaskKanban({ tasks, canCreate, onCreateInColumn, onMove, canMove }: TaskKanbanProps) {
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const [overColumn, setOverColumn] = useState<TaskStatus | null>(null);
    const [optimistic, setOptimistic] = useState<TaskCardData[] | null>(null);

    const grouped = useMemo(() => {
        const source = optimistic ?? tasks;
        return COLUMNS.reduce<Record<TaskStatus, TaskCardData[]>>((acc, col) => {
            acc[col] = source.filter((t) => t.status === col).sort((a, b) => a.position - b.position);
            return acc;
        }, { todo: [], in_progress: [], completed: [] });
    }, [tasks, optimistic]);

    const handleDrop = (e: React.DragEvent, targetStatus: TaskStatus) => {
        e.preventDefault();
        setOverColumn(null);
        const id = Number(e.dataTransfer.getData('text/task-id'));
        if (!id) return;
        const source = (optimistic ?? tasks).find((t) => t.id === id);
        if (!source) return;
        if (canMove && !canMove(source)) {
            setDraggedId(null);
            return;
        }

        const targetCount = (optimistic ?? tasks).filter((t) => t.status === targetStatus).length;
        const newPosition = source.status === targetStatus ? source.position : targetCount + 1;

        const next = (optimistic ?? tasks).map((t) =>
            t.id === id ? { ...t, status: targetStatus, position: newPosition } : t,
        );
        setOptimistic(next);
        setDraggedId(null);

        if (onMove) {
            onMove(id, targetStatus, newPosition);
            return;
        }

        router.patch(
            route('tasks.status', id),
            { status: targetStatus, position: newPosition },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setOptimistic(null),
            },
        );
    };

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            {COLUMNS.map((col) => {
                const meta = TASK_STATUS_META[col];
                const items = grouped[col];
                const isOver = overColumn === col;
                return (
                    <div
                        key={col}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setOverColumn(col);
                        }}
                        onDragLeave={() => setOverColumn((prev) => (prev === col ? null : prev))}
                        onDrop={(e) => handleDrop(e, col)}
                        className={cn(
                            'bg-card/60 ring-border/60 ring-1 relative flex flex-col rounded-2xl backdrop-blur transition-all duration-200',
                            isOver && 'ring-violet-400 dark:ring-violet-500/40 shadow-soft-lg ring-2',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2 border-b border-border/60 p-3">
                            <div className="flex items-center gap-2">
                                <span className={cn('inline-block h-2 w-2 rounded-full', meta.dot)} />
                                <h3 className="font-display text-sm font-bold tracking-tight">{meta.label}</h3>
                                <span className="bg-muted text-muted-foreground inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold tabular-nums">
                                    {items.length}
                                </span>
                            </div>
                            {canCreate && (
                                <button
                                    type="button"
                                    onClick={() => onCreateInColumn?.(col)}
                                    className="text-muted-foreground hover:text-foreground bg-muted/60 hover:bg-muted inline-flex size-7 items-center justify-center rounded-lg transition-all"
                                    title="Add task"
                                >
                                    <Plus className="size-3.5" />
                                </button>
                            )}
                        </div>

                        <div className="scrollbar-soft flex max-h-[68vh] flex-col gap-2.5 overflow-y-auto p-3">
                            {items.length === 0 && (
                                <div className="bg-muted/40 ring-border/40 ring-1 ring-dashed rounded-xl py-8 text-center text-xs text-muted-foreground">
                                    Drop tasks here
                                </div>
                            )}
                            {items.map((t) => (
                                <TaskCard
                                    key={t.id}
                                    task={t}
                                    draggable={!canMove || canMove(t)}
                                    isDragging={draggedId === t.id}
                                    onDragStart={(e, task) => {
                                        e.dataTransfer.setData('text/task-id', String(task.id));
                                        setDraggedId(task.id);
                                    }}
                                    onDragEnd={() => setDraggedId(null)}
                                />
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
