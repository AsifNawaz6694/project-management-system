import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { type TaskCardData } from '@/components/task-card';
import { TaskKanban } from '@/components/task-kanban';
import { TaskList } from '@/components/task-list';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { TASK_PRIORITY_META, type TaskStatus } from '@/lib/tasks';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CheckCircle2, Circle, Kanban, ListChecks, ListTodo, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Tasks', href: '/tasks' },
];

interface TasksIndexProps {
    tasks: TaskCardData[];
    filters: { search?: string; project?: string; priority?: string; assignee?: string; view?: string };
    stats: { total: number; todo: number; in_progress: number; completed: number };
    projects: Array<{ id: number; slug: string; title: string; color: string }>;
    assignees: Array<{ id: number; name: string; initials: string; avatar?: string | null; job_title?: string | null }>;
    statuses: string[];
    priorities: string[];
}

type View = 'kanban' | 'list';

export default function TasksIndex({ tasks, filters, stats, projects, assignees, priorities }: TasksIndexProps) {
    const { user, can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [project, setProject] = useState(filters.project ?? 'all');
    const [priority, setPriority] = useState(filters.priority ?? 'all');
    const [assignee, setAssignee] = useState(filters.assignee ?? 'all');
    const [view, setView] = useState<View>((filters.view as View) ?? 'kanban');

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('tasks.index'),
                {
                    search: search || undefined,
                    project: project === 'all' ? undefined : project,
                    priority: priority === 'all' ? undefined : priority,
                    assignee: assignee === 'all' ? undefined : assignee,
                    view,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 250);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, project, priority, assignee, view]);

    const canMoveTask = (t: TaskCardData) => {
        if (!user) return false;
        if (can('tasks.update')) return true;
        return t.assignee?.id === user.id && can('tasks.update-status');
    };

    const visibleTasks = useMemo(() => tasks, [tasks]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tasks" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Workspace"
                    title="Tasks"
                    description={
                        can('tasks.create')
                            ? 'Plan, prioritize, and move work across the board.'
                            : 'Tasks assigned to you across every project.'
                    }
                    actions={
                        <>
                            <div className="bg-card ring-border/60 shadow-soft-xs flex gap-1 rounded-xl p-1 ring-1">
                                {(['kanban', 'list'] as View[]).map((v) => (
                                    <button
                                        key={v}
                                        onClick={() => setView(v)}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold capitalize transition-all',
                                            view === v
                                                ? 'shadow-soft-sm from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {v === 'kanban' ? <Kanban className="size-3.5" /> : <ListTodo className="size-3.5" />}
                                        {v}
                                    </button>
                                ))}
                            </div>
                            {can('tasks.create') && (
                                <Button asChild className="gap-2">
                                    <Link href={route('tasks.create')}>
                                        <Plus className="size-4" /> New task
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="All tasks" value={stats.total} icon={ListChecks} accent="violet" />
                    <StatCard label="To do" value={stats.todo} icon={Circle} accent="slate" />
                    <StatCard label="In progress" value={stats.in_progress} icon={Activity} accent="amber" />
                    <StatCard label="Completed" value={stats.completed} icon={CheckCircle2} accent="emerald" />
                </section>

                <section className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col gap-3 rounded-2xl p-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute left-3.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search tasks…"
                            className="h-11 pl-10"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={project} onValueChange={setProject}>
                            <SelectTrigger className="h-11 w-[180px] rounded-xl"><SelectValue placeholder="All projects" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All projects</SelectItem>
                                {projects.map((p) => (
                                    <SelectItem key={p.slug} value={p.slug}>{p.title}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={priority} onValueChange={setPriority}>
                            <SelectTrigger className="h-11 w-[150px] rounded-xl"><SelectValue placeholder="All priorities" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All priorities</SelectItem>
                                {priorities.map((p) => (
                                    <SelectItem key={p} value={p}>{TASK_PRIORITY_META[p as keyof typeof TASK_PRIORITY_META]?.label ?? p}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={assignee} onValueChange={setAssignee}>
                            <SelectTrigger className="h-11 w-[180px] rounded-xl"><SelectValue placeholder="Anyone" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">Anyone</SelectItem>
                                <SelectItem value="me">Assigned to me</SelectItem>
                                {assignees.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                {view === 'kanban' ? (
                    <TaskKanban
                        tasks={visibleTasks}
                        canCreate={can('tasks.create')}
                        canMove={canMoveTask}
                        onCreateInColumn={(s: TaskStatus) => router.visit(route('tasks.create', { status: s, project_id: project !== 'all' ? projects.find((p) => p.slug === project)?.id : undefined }))}
                    />
                ) : (
                    <TaskList tasks={visibleTasks} />
                )}
            </div>
        </AppLayout>
    );
}
