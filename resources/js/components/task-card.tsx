import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, isOverdue, relativeDue, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { Link } from '@inertiajs/react';
import { CalendarClock, MessageSquare, Paperclip, ListChecks } from 'lucide-react';

export interface TaskCardData {
    id: number;
    title: string;
    description?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    due_date?: string | null;
    position: number;
    project?: { id: number; slug: string; title: string; color: ProjectColor } | null;
    assignee?: { id: number; name: string; avatar?: string | null } | null;
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
}

export function TaskCard({ task, onDragStart, onDragEnd, isDragging, draggable = false }: TaskCardProps) {
    const getInitials = useInitials();
    const priority = TASK_PRIORITY_META[task.priority] ?? TASK_PRIORITY_META.medium;
    const overdue = isOverdue(task.due_date, task.status);

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
                'group bg-card shadow-soft-xs hover:shadow-soft-md ring-border/60 hover:ring-foreground/20 ring-1 relative flex flex-col gap-2.5 overflow-hidden rounded-xl p-3.5 transition-all duration-300',
                draggable && 'cursor-grab active:cursor-grabbing',
                isDragging && 'shadow-soft-lg ring-violet-400 dark:ring-violet-500/40 -translate-y-0.5 rotate-[1deg] scale-[1.01] ring-2',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <Link
                    href={route('tasks.show', task.id)}
                    className="line-clamp-2 flex-1 text-sm font-semibold leading-snug hover:text-violet-600 dark:hover:text-violet-300"
                >
                    {task.title}
                </Link>
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset',
                        priority.chip,
                    )}
                >
                    {priority.label}
                </span>
            </div>

            {task.description && (
                <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">{task.description}</p>
            )}

            {task.project && (
                <div className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px] font-medium">
                    <span className={cn('size-1.5 rounded-full', COLOR_DOT[task.project.color] ?? 'bg-violet-500')} />
                    <span className="truncate">{task.project.title}</span>
                </div>
            )}

            <div className="flex items-center justify-between gap-2 pt-1">
                <div className="text-muted-foreground flex items-center gap-2.5 text-[11px]">
                    {task.due_date && (
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 font-medium',
                                overdue && 'text-rose-600 dark:text-rose-400',
                            )}
                        >
                            <CalendarClock className="size-3" />
                            {relativeDue(task.due_date)}
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
                {task.assignee ? (
                    <div
                        className="ring-card flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-[9px] font-bold text-white ring-2"
                        title={task.assignee.name}
                    >
                        {getInitials(task.assignee.name)}
                    </div>
                ) : (
                    <span className="text-muted-foreground/70 text-[10px] italic">Unassigned</span>
                )}
            </div>
        </div>
    );
}
