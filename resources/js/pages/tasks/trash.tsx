import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { TASK_PRIORITY_META, fallbackStatus, statusChip, type TaskPriority } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tasks', href: '/tasks' },
    { title: 'Trash', href: '/tasks/trash' },
];

interface TrashedTask {
    id: number;
    key: string;
    title: string;
    status: string;
    priority: TaskPriority;
    project?: { id: number; slug: string; title: string; color: ProjectColor } | null;
    assignee?: { id: number; name: string } | null;
    deleted_at: string | null;
}

interface TrashProps {
    tasks: TrashedTask[];
    pagination: { current_page: number; last_page: number; total: number };
    can: { restore: boolean };
}

export default function TasksTrash({ tasks, pagination, can }: TrashProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Deleted tasks" />

            <div className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Recovery"
                    title="Deleted tasks"
                    description={`${pagination.total} deleted task(s). Restoring brings a task back exactly as it was.`}
                    actions={
                        <Button asChild size="sm" variant="soft">
                            <Link href={route('tasks.index')}>
                                <ArrowLeft className="size-4" /> Back to tasks
                            </Link>
                        </Button>
                    }
                />

                {tasks.length === 0 ? (
                    <div className="bg-card ring-border/60 rounded-2xl p-12 text-center ring-1">
                        <Trash2 className="text-muted-foreground/50 mx-auto size-8" />
                        <p className="text-muted-foreground mt-3 text-sm">The trash is empty.</p>
                    </div>
                ) : (
                    <div className="bg-card ring-border/60 overflow-hidden rounded-2xl ring-1">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-border/60 text-muted-foreground border-b text-left text-[11px] font-bold tracking-[0.12em] uppercase">
                                    <th className="px-3 py-2.5">Task</th>
                                    <th className="w-32 px-3 py-2.5">Status</th>
                                    <th className="w-28 px-3 py-2.5">Priority</th>
                                    <th className="w-40 px-3 py-2.5">Deleted</th>
                                    <th className="w-28 px-3 py-2.5" />
                                </tr>
                            </thead>
                            <tbody className="divide-border/60 divide-y">
                                {tasks.map((task) => {
                                    const status = fallbackStatus(task.status);
                                    const priority = TASK_PRIORITY_META[task.priority] ?? TASK_PRIORITY_META.medium;

                                    return (
                                        <tr key={task.id} className="hover:bg-muted/40 transition-colors">
                                            <td className="px-3 py-2.5">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-muted-foreground font-mono text-[10px] font-semibold">{task.key}</span>
                                                    <span className="truncate font-medium">{task.title}</span>
                                                </div>
                                                {task.project && (
                                                    <span className="text-muted-foreground mt-0.5 inline-flex items-center gap-1 text-[11px]">
                                                        <span
                                                            className={cn('size-1.5 rounded-full', COLOR_DOT[task.project.color] ?? 'bg-blue-600')}
                                                        />
                                                        {task.project.title}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                        statusChip(status.color),
                                                    )}
                                                >
                                                    {status.name}
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
                                            <td className="text-muted-foreground px-3 py-2.5 text-[11px]">
                                                {task.deleted_at ? new Date(task.deleted_at).toLocaleString() : '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-right">
                                                {can.restore && (
                                                    <Button
                                                        size="sm"
                                                        variant="soft"
                                                        onClick={() => router.post(route('tasks.restore', task.id), {}, { preserveScroll: true })}
                                                    >
                                                        <RotateCcw className="size-3.5" /> Restore
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
