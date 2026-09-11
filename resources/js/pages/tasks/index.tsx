import { FilterSelect, toArray } from '@/components/filter-select';
import { PageHeader } from '@/components/page-header';
import { TaskBulkBar } from '@/components/task-bulk-bar';
import { type TaskCardData } from '@/components/task-card';
import { TaskKanban } from '@/components/task-kanban';
import { TaskList } from '@/components/task-list';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { TASK_PRIORITY_META, indexStatuses, type TaskPriority, type WorkflowStatus } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Bookmark, Download, Kanban, ListTodo, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tasks', href: '/tasks' }];

interface Option {
    id: number;
    name?: string;
    title?: string;
    slug?: string;
    key?: string;
    color?: string;
}

interface SavedFilter {
    id: number;
    name: string;
    query: Record<string, unknown>;
    is_shared: boolean;
    is_owner: boolean;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface TasksIndexProps {
    tasks: TaskCardData[];
    pagination: Pagination | null;
    filters: Record<string, string | string[] | undefined>;
    stats: Record<string, number>;
    statuses: WorkflowStatus[];
    projectStatuses: Record<number, string[]>;
    reasonRules: Record<number, Record<string, string>>;
    projects: Option[];
    assignees: Array<{ id: number; name: string }>;
    teams: Option[];
    labels: Option[];
    types: Array<{ id: number; key: string; name: string }>;
    priorities: TaskPriority[];
    savedFilters: SavedFilter[];
    can: { create: boolean; bulkEdit: boolean; export: boolean; lifecycle: boolean };
}

type View = 'kanban' | 'list';

export default function TasksIndex(props: TasksIndexProps) {
    const {
        tasks,
        pagination,
        filters,
        stats,
        statuses,
        projectStatuses,
        reasonRules,
        projects,
        assignees,
        teams,
        labels,
        types,
        priorities,
        savedFilters,
        can,
    } = props;
    const [search, setSearch] = useState((filters.search as string) ?? '');
    const [view, setView] = useState<View>((filters.view as View) ?? 'kanban');
    const [selected, setSelected] = useState<number[]>([]);
    const firstRender = useRef(true);

    const statusIndex = useMemo(() => indexStatuses(statuses), [statuses]);
    const sort = (filters.sort as string) ?? '';
    const direction = (filters.direction as string) ?? 'asc';

    // Every filter is modelled as an array so single and multi selections share
    // one shape in state and in the query string.
    const filterValues = (key: string) => toArray(filters[key]);

    /** Push the current filter set into the URL, letting Inertia refetch. */
    const apply = (patch: Record<string, unknown>, resetPage = true) => {
        const next: Record<string, unknown> = { ...filters, ...patch, view };
        if (resetPage) delete next.page;

        Object.keys(next).forEach((k) => {
            const v = next[k];
            if (v === 'all' || v === '' || v == null || (Array.isArray(v) && v.length === 0)) delete next[k];
        });

        router.get(route('tasks.index'), next as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Debounce the search box so typing does not fire a request per keystroke.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const id = setTimeout(() => apply({ search: search || undefined }), 300);
        return () => clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    useEffect(() => setSelected([]), [tasks]);

    const toggleSelect = (id: number) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const toggleAll = () => setSelected((prev) => (prev.length === tasks.length ? [] : tasks.map((t) => t.id)));

    const onSort = (column: string) => {
        const nextDirection = sort === column && direction === 'asc' ? 'desc' : 'asc';
        apply({ sort: column, direction: nextDirection });
    };

    const exportUrl = route('tasks.export', filters as never);

    const activeFilterCount = Object.keys(filters).filter((k) => !['view', 'sort', 'direction', 'page'].includes(k)).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tasks" />

            <div className="flex w-full max-w-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 sm:p-4 md:gap-5 md:p-6">
                <PageHeader
                    eyebrow="Work"
                    title="Tasks"
                    description={pagination ? `${pagination.total} task(s)` : `${tasks.length} task(s) on the board`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.export && (
                                <Button asChild size="sm" variant="soft">
                                    <a href={exportUrl}>
                                        <Download className="size-4" /> Export CSV
                                    </a>
                                </Button>
                            )}
                            {can.lifecycle && (
                                <Button asChild size="sm" variant="ghost">
                                    <Link href={route('tasks.trash')}>
                                        <Trash2 className="size-4" /> Trash
                                    </Link>
                                </Button>
                            )}
                            {can.create && (
                                <Button asChild size="sm">
                                    <Link href={route('tasks.create')}>
                                        <Plus className="size-4" /> New task
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Status summary — scrolls within itself on a narrow screen
                    rather than pushing the page wide. */}
                <div className="scrollbar-soft -mx-1 flex max-w-full min-w-0 gap-1.5 overflow-x-auto px-1 pb-1 md:flex-wrap md:overflow-visible">
                    {statuses.map((s) => (
                        <button
                            key={s.key}
                            type="button"
                            onClick={() => {
                                const current = filterValues('status');
                                apply({
                                    status: current.includes(s.key) ? current.filter((k) => k !== s.key) : [...current, s.key],
                                });
                            }}
                            className={cn(
                                'bg-card ring-border/60 hover:ring-foreground/20 shrink-0 rounded-lg px-2.5 py-1.5 text-left ring-1 transition-all',
                                filterValues('status').includes(s.key) && 'ring-primary ring-2',
                            )}
                        >
                            <span className="text-muted-foreground block max-w-24 truncate text-[9px] font-bold tracking-[0.08em] uppercase">
                                {s.name}
                            </span>
                            <span className="font-display text-base font-bold tabular-nums">{stats[s.key] ?? 0}</span>
                        </button>
                    ))}
                </div>

                {/* Filters */}
                <div className="bg-card ring-border/60 flex max-w-full min-w-0 flex-wrap items-center gap-1.5 rounded-xl p-2 ring-1 sm:gap-2 sm:rounded-2xl sm:p-3">
                    <div className="relative w-full min-w-[180px] sm:w-auto sm:flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search title, description or key (e.g. WEB-12)"
                            className="pl-9"
                        />
                    </div>

                    <FilterSelect
                        label="Projects"
                        className="w-[8.5rem] sm:w-[10rem]"
                        value={filterValues('project')}
                        onChange={(v) => apply({ project: v })}
                        options={projects.map((p) => ({ value: p.slug!, label: p.title!, hint: p.key, color: p.color }))}
                    />
                    <FilterSelect
                        label="Priorities"
                        className="w-[7.5rem] sm:w-[8.5rem]"
                        value={filterValues('priority')}
                        onChange={(v) => apply({ priority: v })}
                        options={priorities.map((p) => ({ value: p, label: TASK_PRIORITY_META[p].label }))}
                    />
                    <FilterSelect
                        label="Assignees"
                        className="w-[8.5rem] sm:w-[10rem]"
                        value={filterValues('assignee')}
                        onChange={(v) => apply({ assignee: v })}
                        options={[
                            { value: 'me', label: 'Assigned to me' },
                            { value: 'unassigned', label: 'Unassigned' },
                            ...assignees.map((a) => ({ value: String(a.id), label: a.name })),
                        ]}
                    />
                    <FilterSelect
                        label="Teams"
                        className="w-[7.5rem] sm:w-[9rem]"
                        value={filterValues('team')}
                        onChange={(v) => apply({ team: v })}
                        options={[
                            { value: 'mine', label: 'My teams' },
                            ...teams.map((t) => ({ value: String(t.id), label: t.name!, color: t.color })),
                        ]}
                    />
                    <FilterSelect
                        label="Labels"
                        className="w-[7.5rem] sm:w-[9rem]"
                        value={filterValues('label')}
                        onChange={(v) => apply({ label: v })}
                        options={labels.map((l) => ({ value: l.slug!, label: l.name!, color: l.color }))}
                    />
                    <FilterSelect
                        label="Types"
                        className="w-[7.5rem] sm:w-[9rem]"
                        value={filterValues('type')}
                        onChange={(v) => apply({ type: v })}
                        options={types.map((t) => ({ value: t.key, label: t.name }))}
                    />

                    <label className="text-muted-foreground inline-flex items-center gap-1.5 text-xs font-medium">
                        <input
                            type="checkbox"
                            checked={!!filters.overdue}
                            onChange={(e) => apply({ overdue: e.target.checked ? 1 : undefined })}
                            className="accent-primary size-3.5"
                        />
                        Overdue
                    </label>

                    {activeFilterCount > 0 && (
                        <Button size="sm" variant="ghost" onClick={() => router.get(route('tasks.index'), { view } as never, { replace: true })}>
                            <X className="size-3.5" /> Clear
                        </Button>
                    )}

                    <div className="bg-muted ml-auto flex items-center gap-0.5 rounded-lg p-0.5">
                        {(['kanban', 'list'] as View[]).map((v) => (
                            <button
                                key={v}
                                type="button"
                                onClick={() => {
                                    setView(v);
                                    apply({ view: v }, false);
                                }}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors',
                                    view === v ? 'bg-card shadow-soft-xs' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {v === 'kanban' ? <Kanban className="size-3.5" /> : <ListTodo className="size-3.5" />}
                                {v === 'kanban' ? 'Board' : 'List'}
                            </button>
                        ))}
                    </div>
                </div>

                <SavedFilterBar
                    filters={filters}
                    savedFilters={savedFilters}
                    onApply={(q) => router.get(route('tasks.index'), { ...q, view } as never, { replace: true })}
                />

                {view === 'kanban' ? (
                    <TaskKanban
                        tasks={tasks}
                        statuses={statuses}
                        projectStatuses={projectStatuses}
                        reasonRules={reasonRules}
                        canCreate={can.create}
                        onCreateInColumn={(status) =>
                            router.visit(
                                route('tasks.create', {
                                    status,
                                    project_id: projects.find((p) => p.slug === filterValues('project')[0])?.id,
                                }),
                            )
                        }
                        selectedIds={can.bulkEdit ? selected : undefined}
                        onToggleSelect={can.bulkEdit ? toggleSelect : undefined}
                    />
                ) : (
                    <>
                        <TaskList
                            tasks={tasks}
                            statuses={statusIndex}
                            sort={sort}
                            direction={direction}
                            onSort={onSort}
                            selectedIds={can.bulkEdit ? selected : undefined}
                            onToggleSelect={can.bulkEdit ? toggleSelect : undefined}
                            onToggleAll={can.bulkEdit ? toggleAll : undefined}
                        />

                        {pagination && pagination.last_page > 1 && (
                            <nav className="flex items-center justify-between gap-3">
                                <p className="text-muted-foreground text-xs">
                                    Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total}
                                </p>
                                <div className="flex items-center gap-1.5">
                                    <Button
                                        size="sm"
                                        variant="soft"
                                        disabled={pagination.current_page <= 1}
                                        onClick={() => apply({ page: pagination.current_page - 1 }, false)}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-muted-foreground px-2 text-xs tabular-nums">
                                        {pagination.current_page} / {pagination.last_page}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="soft"
                                        disabled={pagination.current_page >= pagination.last_page}
                                        onClick={() => apply({ page: pagination.current_page + 1 }, false)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </nav>
                        )}
                    </>
                )}
            </div>

            {can.bulkEdit && selected.length > 0 && (
                <TaskBulkBar
                    selected={selected}
                    statuses={statuses}
                    assignees={assignees}
                    teams={teams}
                    labels={labels}
                    priorities={priorities}
                    canManageLifecycle={can.lifecycle}
                    onClear={() => setSelected([])}
                />
            )}
        </AppLayout>
    );
}

function SavedFilterBar({
    filters,
    savedFilters,
    onApply,
}: {
    filters: Record<string, unknown>;
    savedFilters: SavedFilter[];
    onApply: (query: Record<string, unknown>) => void;
}) {
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    const save = () => {
        if (!name.trim()) return;
        setSaving(true);
        router.post(
            route('task-filters.store'),
            { name: name.trim(), query: filters as never, is_shared: false },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setName('');
                },
            },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            {savedFilters.map((f) => (
                <span key={f.id} className="bg-card ring-border/60 inline-flex items-center gap-1 rounded-full py-1 pr-1 pl-2.5 text-xs ring-1">
                    <button
                        type="button"
                        onClick={() => onApply(f.query)}
                        className="hover:text-primary inline-flex items-center gap-1.5 font-medium"
                    >
                        <Bookmark className="size-3" />
                        {f.name}
                    </button>
                    {f.is_owner && (
                        <button
                            type="button"
                            aria-label={`Delete filter ${f.name}`}
                            onClick={() => router.delete(route('task-filters.destroy', f.id), { preserveScroll: true })}
                            className="text-muted-foreground hover:text-red-600"
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </span>
            ))}

            <div className="flex items-center gap-1">
                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Save current filters as…" className="h-8 w-52 text-xs" />
                <Button size="sm" variant="soft" onClick={save} disabled={!name.trim() || saving}>
                    Save
                </Button>
            </div>
        </div>
    );
}
