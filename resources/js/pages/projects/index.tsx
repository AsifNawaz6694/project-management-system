import { FilterSelect, toArray } from '@/components/filter-select';
import { PageHeader } from '@/components/page-header';
import { ProjectCard, type ProjectCardData } from '@/components/project-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    const [status, setStatus] = useState<string[]>(toArray(filters.status));
    const [priority, setPriority] = useState<string[]>(toArray(filters.priority));

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('projects.index'),
                {
                    search: search || undefined,
                    status: status.length ? status : undefined,
                    priority: priority.length ? priority : undefined,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 250);
        return () => clearTimeout(handle);
    }, [search, status, priority]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
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

                <section className="bg-card shadow-soft-sm ring-border/60 flex flex-col gap-3 rounded-2xl p-3 ring-1 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search projects by title or description…"
                            className="h-11 pl-10"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <FilterSelect
                            label="Statuses"
                            className="w-[10rem]"
                            value={status}
                            onChange={setStatus}
                            options={statuses.map((s) => ({
                                value: s,
                                label: STATUS_META[s as keyof typeof STATUS_META]?.label ?? s,
                                color: STATUS_META[s as keyof typeof STATUS_META]?.dot?.replace('bg-', '').split('-')[0],
                            }))}
                        />
                        <FilterSelect
                            label="Priorities"
                            className="w-[10rem]"
                            value={priority}
                            onChange={setPriority}
                            options={priorities.map((p) => ({
                                value: p,
                                label: PRIORITY_META[p as keyof typeof PRIORITY_META]?.label ?? p,
                            }))}
                        />
                    </div>
                </section>

                {projects.data.length === 0 ? (
                    <div className="bg-card shadow-soft-sm ring-border/60 flex flex-col items-center justify-center gap-3 rounded-2xl p-16 text-center ring-1">
                        <div className="shadow-glow flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 text-white">
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
                                        ? 'shadow-soft-md bg-gradient-to-br from-blue-600 to-blue-700 text-white'
                                        : 'bg-card ring-border text-muted-foreground hover:text-foreground hover:shadow-soft-sm ring-1')
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
