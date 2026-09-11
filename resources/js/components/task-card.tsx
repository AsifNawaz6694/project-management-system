import { useInitials } from '@/hooks/use-initials';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import {
    TASK_PRIORITY_META,
    isOverdue,
    relativeDue,
    statusDot,
    type TaskLabel,
    type TaskPriority,
    type TaskStatus,
    type TaskTypeMeta,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { CalendarClock, ListChecks, MessageSquare, Paperclip, Users } from 'lucide-react';

export interface TaskCardData {
    id: number;
    key?: string;
    title: string;
    description?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    due_date?: string | null;
    completed_at?: string | null;
    position: number;
    project?: { id: number; slug: string; key?: string; title: string; color: ProjectColor } | null;
    assignee?: { id: number; name: string; avatar?: string | null } | null;
    team?: { id: number; name: string; color?: string } | null;
    type?: TaskTypeMeta | null;
    labels?: TaskLabel[];
    subtasks_count?: number;
    comments_count?: number;
    attachments_count?: number;
}

interface TaskCardProps {
    task: TaskCardData;
    onDragStart?: (e: React.DragEvent, task: TaskCardData) => void;
    onDragEnd?: () => void;
    isDragging?: boolean;
    draggable?: boolean;
    selected?: boolean;
    onToggleSelect?: (id: number) => void;
}

export function TaskCard({ task, onDragStart, onDragEnd, isDragging, draggable = false, selected, onToggleSelect }: TaskCardProps) {
    const getInitials = useInitials();
    const priority = TASK_PRIORITY_META[task.priority] ?? TASK_PRIORITY_META.medium;
    // Overdue is now derived from completion, not from a status string.
    const overdue = isOverdue(task.due_date, task.completed_at);

    return (
        <div
            draggable={draggable}
            onDragStart={(e) => {
                if (onDragStart) {
                    e.dataTransfer.effectAllowed = 'move';
                    onDragStart(e, task);
                }
            }}
            onDragEnd={onDragEnd}
            className={cn(
                'group bg-card shadow-soft-xs hover:shadow-soft-md ring-border/60 hover:ring-foreground/20 relative flex flex-col gap-2 overflow-hidden rounded-lg p-2.5 ring-1 transition-all duration-300',
                draggable && 'cursor-grab active:cursor-grabbing',
                selected && 'ring-primary ring-2',
                isDragging && 'shadow-soft-lg ring-primary -translate-y-0.5 scale-[1.01] rotate-[1deg] ring-2',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 flex-1 items-start gap-2">
                    {onToggleSelect && (
                        <input
                            type="checkbox"
                            checked={selected ?? false}
                            onChange={() => onToggleSelect(task.id)}
                            onClick={(e) => e.stopPropagation()}
                            aria-label={`Select ${task.key ?? task.title}`}
                            className="accent-primary mt-0.5 size-3.5 shrink-0"
                        />
                    )}
                    <div className="min-w-0 flex-1">
                        {task.key && <span className="text-muted-foreground font-mono text-[10px] font-semibold tracking-tight">{task.key}</span>}
                        <Link
                            href={route('tasks.show', task.id)}
                            className="hover:text-primary line-clamp-2 block text-sm leading-snug font-semibold"
                        >
                            {task.title}
                        </Link>
                    </div>
                </div>
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase ring-1 ring-inset',
                        priority.chip,
                    )}
                >
                    {priority.label}
                </span>
            </div>

            {(task.type || (task.labels?.length ?? 0) > 0) && (
                <div className="flex flex-wrap items-center gap-1">
                    {task.type && (
                        <span className="bg-muted text-muted-foreground inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold">
                            {task.type.name}
                        </span>
                    )}
                    {task.labels?.slice(0, 2).map((label) => (
                        <span
                            key={label.id}
                            className="ring-border/60 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset"
                        >
                            <span className={cn('size-1.5 rounded-full', statusDot(label.color))} />
                            {label.name}
                        </span>
                    ))}
                    {(task.labels?.length ?? 0) > 3 && <span className="text-muted-foreground text-[10px]">+{(task.labels?.length ?? 0) - 3}</span>}
                </div>
            )}

            {task.project && (
                <div className="text-muted-foreground flex min-w-0 items-center gap-1.5 text-[10px] font-medium">
                    <span className={cn('size-1.5 shrink-0 rounded-full', COLOR_DOT[task.project.color] ?? 'bg-blue-600')} />
                    <span className="truncate">{task.project.title}</span>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-0.5">
                <div className="text-muted-foreground flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px]">
                    {task.due_date && (
                        <span
                            title={relativeDue(task.due_date)}
                            className={cn('inline-flex min-w-0 items-center gap-1 font-medium', overdue && 'text-red-600 dark:text-red-400')}
                        >
                            <CalendarClock className="size-3 shrink-0" />
                            <span className="truncate">{relativeDue(task.due_date)}</span>
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

                <div className="flex items-center gap-1">
                    {task.team && (
                        <span
                            className="bg-muted text-muted-foreground hidden items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold xl:inline-flex"
                            title={`Team: ${task.team.name}`}
                        >
                            <Users className="size-2.5" />
                            <span className="max-w-16 truncate">{task.team.name}</span>
                        </span>
                    )}
                    {task.assignee ? (
                        <div
                            className="ring-card flex size-5 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[8px] font-bold text-white ring-2"
                            title={task.assignee.name}
                        >
                            {getInitials(task.assignee.name)}
                        </div>
                    ) : (
                        <span className="text-muted-foreground/70 hidden text-[10px] italic lg:inline">Unassigned</span>
                    )}
                </div>
            </div>
        </div>
    );
}
