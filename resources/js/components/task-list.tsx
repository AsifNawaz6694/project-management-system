import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, TASK_STATUS_META, isOverdue, relativeDue, type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { type TaskCardData } from '@/components/task-card';
import { Link } from '@inertiajs/react';
import { CalendarClock, ListChecks, MessageSquare, Paperclip } from 'lucide-react';

interface TaskListProps {
    tasks: TaskCardData[];
}

export function TaskList({ tasks }: TaskListProps) {
    const getInitials = useInitials();

    return (
        <div className="bg-card ring-border/60 shadow-soft-sm overflow-hidden rounded-2xl ring-1">
            <div className="bg-muted/40 hidden grid-cols-[1fr_140px_140px_140px_140px_120px] gap-3 px-5 py-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground md:grid">
                <span>Task</span>
                <span>Project</span>
                <span>Status</span>
                <span>Priority</span>
                <span>Due</span>
                <span className="text-right">Assignee</span>
            </div>
            <ul className="divide-y divide-border/60">
                {tasks.length === 0 && (
                    <li className="p-10 text-center text-sm text-muted-foreground">No tasks match your filters.</li>
                )}
                {tasks.map((t) => {
                    const status = TASK_STATUS_META[t.status as TaskStatus];
                    const priority = TASK_PRIORITY_META[t.priority as TaskPriority];
                    const overdue = isOverdue(t.due_date, t.status);
                    return (
                        <li key={t.id}>
                            <Link
                                href={route('tasks.show', t.id)}
                                className="group hover:bg-muted/40 grid grid-cols-1 gap-2 px-5 py-3.5 transition-colors md:grid-cols-[1fr_140px_140px_140px_140px_120px] md:gap-3 md:items-center"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold group-hover:text-violet-600 dark:group-hover:text-violet-300">{t.title}</p>
                                    <div className="text-muted-foreground mt-0.5 flex items-center gap-3 text-[11px]">
                                        {(t.subtasks_count ?? 0) > 0 && <span className="inline-flex items-center gap-1"><ListChecks className="size-3" />{t.subtasks_count}</span>}
                                        {(t.comments_count ?? 0) > 0 && <span className="inline-flex items-center gap-1"><MessageSquare className="size-3" />{t.comments_count}</span>}
                                        {(t.attachments_count ?? 0) > 0 && <span className="inline-flex items-center gap-1"><Paperclip className="size-3" />{t.attachments_count}</span>}
                                    </div>
                                </div>

                                <div className="text-muted-foreground inline-flex items-center gap-2 text-xs">
                                    {t.project && <span className={cn('size-1.5 rounded-full', COLOR_DOT[t.project.color as ProjectColor] ?? 'bg-violet-500')} />}
                                    <span className="truncate">{t.project?.title ?? '—'}</span>
                                </div>

                                <span className={cn('inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', status?.chip)}>
                                    <span className={cn('size-1 rounded-full', status?.dot)} />
                                    {status?.label}
                                </span>

                                <span className={cn('inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', priority?.chip)}>
                                    {priority?.label}
                                </span>

                                <span className={cn('inline-flex items-center gap-1 text-xs', overdue && 'text-rose-600 dark:text-rose-400 font-semibold')}>
                                    <CalendarClock className="size-3.5" />
                                    {relativeDue(t.due_date)}
                                </span>

                                <div className="flex items-center justify-end">
                                    {t.assignee ? (
                                        <div className="ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-[10px] font-bold text-white ring-2" title={t.assignee.name}>
                                            {getInitials(t.assignee.name)}
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground/70 text-[10px] italic">Unassigned</span>
                                    )}
                                </div>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
