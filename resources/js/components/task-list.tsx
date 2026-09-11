import { type TaskCardData } from '@/components/task-card';
import { useInitials } from '@/hooks/use-initials';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, isOverdue, relativeDue, statusChip, statusDot, type TaskPriority, type WorkflowStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, CalendarClock, ListChecks, MessageSquare, Paperclip } from 'lucide-react';

interface TaskListProps {
    tasks: TaskCardData[];
    statuses: Record<string, WorkflowStatus>;
    sort?: string;
    direction?: string;
    onSort?: (column: string) => void;
    selectedIds?: number[];
    onToggleSelect?: (id: number) => void;
    onToggleAll?: () => void;
}

const COLUMNS: Array<{ key: string; label: string; sortable?: boolean; className?: string }> = [
    { key: 'title', label: 'Task', sortable: true },
    { key: 'status', label: 'Status', sortable: true, className: 'w-32' },
    { key: 'priority', label: 'Priority', sortable: true, className: 'w-28' },
    { key: 'due_date', label: 'Due', sortable: true, className: 'w-36' },
    { key: 'assignee', label: 'Assignee', className: 'w-32' },
];

export function TaskList({ tasks, statuses, sort, direction, onSort, selectedIds, onToggleSelect, onToggleAll }: TaskListProps) {
    const getInitials = useInitials();
    const allSelected = tasks.length > 0 && selectedIds?.length === tasks.length;

    if (tasks.length === 0) {
        return (
            <div className="bg-card ring-border/60 rounded-2xl p-10 text-center ring-1">
                <p className="text-muted-foreground text-sm">No tasks match these filters.</p>
            </div>
        );
    }

    return (
        <div className="bg-card ring-border/60 overflow-hidden rounded-2xl ring-1">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-border/60 text-muted-foreground border-b text-left text-[11px] font-bold tracking-[0.12em] uppercase">
                            {onToggleSelect && (
                                <th className="w-10 px-3 py-2.5">
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        onChange={onToggleAll}
                                        aria-label="Select all tasks"
                                        className="accent-primary size-3.5"
                                    />
                                </th>
                            )}
                            {COLUMNS.map((col) => (
                                <th key={col.key} className={cn('px-3 py-2.5', col.className)}>
                                    {col.sortable && onSort ? (
                                        <button
                                            type="button"
                                            onClick={() => onSort(col.key)}
                                            className="hover:text-foreground inline-flex items-center gap-1 transition-colors"
                                        >
                                            {col.label}
                                            {sort === col.key &&
                                                (direction === 'desc' ? <ArrowDown className="size-3" /> : <ArrowUp className="size-3" />)}
                                        </button>
                                    ) : (
                                        col.label
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-border/60 divide-y">
                        {tasks.map((task) => {
                            const priority = TASK_PRIORITY_META[task.priority as TaskPriority] ?? TASK_PRIORITY_META.medium;
                            const status = statuses[task.status];
                            const overdue = isOverdue(task.due_date, task.completed_at);
                            const selected = selectedIds?.includes(task.id);

                            return (
                                <tr key={task.id} className={cn('hover:bg-muted/40 transition-colors', selected && 'bg-primary/5')}>
                                    {onToggleSelect && (
                                        <td className="px-3 py-2.5">
                                            <input
                                                type="checkbox"
                                                checked={selected ?? false}
                                                onChange={() => onToggleSelect(task.id)}
                                                aria-label={`Select ${task.key ?? task.title}`}
                                                className="accent-primary size-3.5"
                                            />
                                        </td>
                                    )}
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-2">
                                            {task.key && (
                                                <span className="text-muted-foreground shrink-0 font-mono text-[10px] font-semibold">{task.key}</span>
                                            )}
                                            <Link href={route('tasks.show', task.id)} className="hover:text-primary truncate font-medium">
                                                {task.title}
                                            </Link>
                                        </div>
                                        <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-[11px]">
                                            {task.project && (
                                                <span className="inline-flex items-center gap-1">
                                                    <span
                                                        className={cn(
                                                            'size-1.5 rounded-full',
                                                            COLOR_DOT[task.project.color as ProjectColor] ?? 'bg-blue-600',
                                                        )}
                                                    />
                                                    {task.project.title}
                                                </span>
                                            )}
                                            {(task.subtasks_count ?? 0) > 0 && (
                                                <span className="inline-flex items-center gap-1">
                                                    <ListChecks className="size-3" />
                                                    {task.subtasks_count}
                                                </span>
                                            )}
                                            {(task.comments_count ?? 0) > 0 && (
                                                <span className="inline-flex items-center gap-1">
                                                    <MessageSquare className="size-3" />
                                                    {task.comments_count}
                                                </span>
                                            )}
                                            {(task.attachments_count ?? 0) > 0 && (
                                                <span className="inline-flex items-center gap-1">
                                                    <Paperclip className="size-3" />
                                                    {task.attachments_count}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                statusChip(status?.color),
                                            )}
                                        >
                                            <span className={cn('size-1.5 rounded-full', statusDot(status?.color))} />
                                            {status?.name ?? task.status}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span
                                            className={cn(
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset',
                                                priority.chip,
                                            )}
                                        >
                                            {priority.label}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {task.due_date ? (
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1 text-[11px] font-medium',
                                                    overdue && 'text-red-600 dark:text-red-400',
                                                )}
                                            >
                                                <CalendarClock className="size-3" />
                                                {relativeDue(task.due_date)}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground text-[11px]">—</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {task.assignee ? (
                                            <span className="inline-flex items-center gap-1.5 text-[11px]">
                                                <span className="ring-card flex size-5 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[8px] font-bold text-white ring-2">
                                                    {getInitials(task.assignee.name)}
                                                </span>
                                                <span className="truncate">{task.assignee.name}</span>
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground/70 text-[11px] italic">Unassigned</span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
