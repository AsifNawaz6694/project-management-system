import { PageHeader } from '@/components/page-header';
import { ProjectCard, type ProjectCardData } from '@/components/project-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_META, STATUS_META } from '@/lib/projects';
import { type BreadcrumbItem, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CheckCircle2, FolderKanban, Plus, Rocket, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Projects', href: '/projects' },
];

interface ProjectsIndexProps {
    projects: PaginatedResponse<ProjectCardData>;
    filters: { search?: string; status?: string; priority?: string };
    stats: { total: number; active: number; planning: number; completed: number };
    statuses: string[];
    priorities: string[];
}

export default function ProjectsIndex({ projects, filters, stats, statuses, priorities }: ProjectsIndexProps) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [priority, setPriority] = useState(filters.priority ?? 'all');

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('projects.index'),
                {
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                    priority: priority === 'all' ? undefined : priority,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 250);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, status, priority]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    eyebrow="Workspace"
                    title="Projects"
                    description={
                        can('projects.create')
                            ? 'Plan, deliver, and track every initiative across the workspace.'
                            : 'A live view of every project you are involved in.'
                    }
                    actions={
                        can('projects.create') && (
                            <Button asChild className="gap-2">
                                <Link href={route('projects.create')}>
                                    <Plus className="size-4" /> New project
                                </Link>
                            </Button>
                        )
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="All projects" value={stats.total} icon={FolderKanban} accent="violet" />
                    <StatCard label="Active" value={stats.active} icon={Activity} accent="emerald" />
                    <StatCard label="Planning" value={stats.planning} icon={Rocket} accent="blue" />
                    <StatCard label="Completed" value={stats.completed} icon={CheckCircle2} accent="amber" />
                </section>

                <section className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col gap-3 rounded-2xl p-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute left-3.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search projects by title or description…"
                            className="h-11 pl-10"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_META[s as keyof typeof STATUS_META]?.label ?? s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={priority} onValueChange={setPriority}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl">
                                <SelectValue placeholder="All priorities" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All priorities</SelectItem>
                                {priorities.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {PRIORITY_META[p as keyof typeof PRIORITY_META]?.label ?? p}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                {projects.data.length === 0 ? (
                    <div className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col items-center justify-center gap-3 rounded-2xl p-16 text-center">
                        <div className="from-violet-500 to-indigo-600 shadow-glow flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br text-white">
                            <FolderKanban className="size-6" />
                        </div>
                        <div>
                            <p className="font-display text-lg font-bold">No projects yet</p>
                            <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                {can('projects.create')
                                    ? 'Spin up your first project to start planning milestones and inviting members.'
                                    : 'Once a manager assigns you to a project, it will show up here.'}
                            </p>
                        </div>
                        {can('projects.create') && (
                            <Button asChild className="mt-2 gap-2">
                                <Link href={route('projects.create')}>
                                    <Plus className="size-4" /> Create project
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <section className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {projects.data.map((p) => (
                            <ProjectCard key={p.id} project={p} />
                        ))}
                    </section>
                )}

                {projects.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-1.5">
                        {projects.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                disabled={!link.url}
                                className={
                                    'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all ' +
                                    (link.active
                                        ? 'shadow-soft-md from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                        : 'bg-card ring-border ring-1 text-muted-foreground hover:text-foreground hover:shadow-soft-sm')
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
