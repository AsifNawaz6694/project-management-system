import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { statusChip, statusDot } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ChevronDown, ChevronUp, Flag, Info, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface StatusRow {
    id: number;
    key: string;
    name: string;
    category: string;
    color: string;
    position: number;
    is_initial: boolean;
    task_count: number;
}

interface TransitionRow {
    from: string | null;
    to: string;
    requires_comment: boolean;
    comment_label: string | null;
    required_permission: string | null;
}

interface ProjectRow {
    id: number;
    key: string;
    title: string;
    assigned: boolean;
}

/** A person or a team that can be narrowed to this chain. */
interface SubjectRow {
    id: number;
    name: string;
    /** Job title or email for a person, slug for a team. */
    detail?: string | null;
    assigned: boolean;
    /** Name of the chain they follow today, when it is not this one. */
    other_chain: string | null;
}

interface PersonRow {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    assigned: boolean;
    other_chain: string | null;
}

interface TeamRow {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    assigned: boolean;
    other_chain: string | null;
}

interface WorkflowEditProps {
    workflow: { id: number; name: string; description: string | null; is_default: boolean; is_system: boolean };
    statuses: StatusRow[];
    transitions: TransitionRow[];
    projects: ProjectRow[];
    people: PersonRow[];
    teams: TeamRow[];
    categories: string[];
    colors: string[];
}

const CATEGORY_LABEL: Record<string, string> = {
    todo: 'Not started',
    in_progress: 'In flight',
    done: 'Finished',
};

/** Wildcard key for the "from any stage" row. */
const ANY = '*';

export default function WorkflowEdit({ workflow, statuses, transitions, projects, people, teams, categories, colors }: WorkflowEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workflows', href: '/workflows' },
        { title: workflow.name, href: route('workflows.edit', workflow.id) },
    ];

    // Transition edits are staged locally and saved as one set.
    const [rules, setRules] = useState<Record<string, TransitionRow>>(() =>
        transitions.reduce<Record<string, TransitionRow>>((acc, t) => {
            acc[`${t.from ?? ANY}>${t.to}`] = t;
            return acc;
        }, {}),
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [expanded, setExpanded] = useState<string | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<StatusRow | null>(null);

    const ordered = useMemo(() => [...statuses].sort((a, b) => a.position - b.position), [statuses]);
    const unrestricted = Object.keys(rules).length === 0;

    const ruleFor = (from: string, to: string) => rules[`${from}>${to}`];

    const toggleRule = (from: string, to: string) => {
        const key = `${from}>${to}`;
        setRules((prev) => {
            const next = { ...prev };
            if (next[key]) {
                delete next[key];
            } else {
                next[key] = { from: from === ANY ? null : from, to, requires_comment: false, comment_label: null, required_permission: null };
            }
            return next;
        });
        setDirty(true);
    };

    const patchRule = (from: string, to: string, patch: Partial<TransitionRow>) => {
        const key = `${from}>${to}`;
        setRules((prev) => (prev[key] ? { ...prev, [key]: { ...prev[key], ...patch } } : prev));
        setDirty(true);
    };

    const saveTransitions = () => {
        setSaving(true);
        router.put(
            route('workflows.transitions.update', workflow.id),
            { transitions: Object.values(rules) as never },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const move = (index: number, direction: -1 | 1) => {
        const next = [...ordered];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];

        router.post(route('workflows.statuses.reorder', workflow.id), { ids: next.map((s) => s.id) }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${workflow.name}`} />

            <div className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Workflow"
                    title={workflow.name}
                    description={workflow.description ?? 'Define the stages and the rules for moving between them.'}
                    actions={
                        <Button asChild size="sm" variant="soft">
                            <Link href={route('workflows.index')}>
                                <ArrowLeft className="size-4" /> All workflows
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div className="space-y-5">
                        {/* ---------------------------------------------- stages */}
                        <SoftCard>
                            <SoftCardTitle eyebrow="Stages">Pipeline</SoftCardTitle>
                            <SoftCardBody className="space-y-2">
                                {ordered.map((status, i) => (
                                    <StatusEditor
                                        key={status.id}
                                        status={status}
                                        workflowId={workflow.id}
                                        categories={categories}
                                        colors={colors}
                                        isFirst={i === 0}
                                        isLast={i === ordered.length - 1}
                                        onMoveUp={() => move(i, -1)}
                                        onMoveDown={() => move(i, 1)}
                                        onDelete={() => setConfirmDelete(status)}
                                    />
                                ))}

                                <AddStatus workflowId={workflow.id} categories={categories} colors={colors} />
                            </SoftCardBody>
                        </SoftCard>

                        {/* ----------------------------------------- transitions */}
                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Rules"
                                action={
                                    <Button size="sm" onClick={saveTransitions} disabled={!dirty || saving}>
                                        <Save className="size-3.5" /> {saving ? 'Saving…' : 'Save rules'}
                                    </Button>
                                }
                            >
                                Allowed transitions
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-3">
                                <div className="text-muted-foreground bg-muted/40 flex items-start gap-2 rounded-xl p-3 text-xs">
                                    <Info className="mt-0.5 size-4 shrink-0" />
                                    <div>
                                        <p>
                                            Tick the stages a task may move to. <strong>Leave everything unticked for unrestricted movement</strong> —
                                            rules only apply once at least one exists.
                                        </p>
                                        {unrestricted && (
                                            <p className="mt-1 font-semibold">Currently unrestricted: any stage can move to any other.</p>
                                        )}
                                    </div>
                                </div>

                                {[...ordered.map((s) => s.key), ANY].map((from) => {
                                    const fromStatus = ordered.find((s) => s.key === from);
                                    const label = from === ANY ? 'From any stage' : `From ${fromStatus?.name}`;

                                    return (
                                        <div key={from} className="ring-border/60 rounded-xl p-3 ring-1">
                                            <p className="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-[0.1em] uppercase">
                                                {fromStatus && <span className={cn('size-2 rounded-full', statusDot(fromStatus.color))} />}
                                                {label}
                                            </p>

                                            <div className="flex flex-wrap gap-1.5">
                                                {ordered
                                                    .filter((s) => s.key !== from)
                                                    .map((to) => {
                                                        const rule = ruleFor(from, to.key);
                                                        const cellKey = `${from}>${to.key}`;

                                                        return (
                                                            <div key={to.key} className="flex flex-col">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleRule(from, to.key)}
                                                                    className={cn(
                                                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 transition-all ring-inset',
                                                                        rule
                                                                            ? statusChip(to.color) + ' ring-2'
                                                                            : 'bg-muted/40 text-muted-foreground ring-border/60 hover:ring-foreground/20',
                                                                    )}
                                                                >
                                                                    <ArrowRight className="size-3" />
                                                                    {to.name}
                                                                    {rule?.requires_comment && (
                                                                        <span className="ml-0.5 text-[10px] font-bold">✎</span>
                                                                    )}
                                                                </button>

                                                                {rule && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setExpanded(expanded === cellKey ? null : cellKey)}
                                                                        className="text-muted-foreground hover:text-foreground mt-0.5 text-[10px] underline underline-offset-2"
                                                                    >
                                                                        {rule.requires_comment ? 'reason required' : 'options'}
                                                                    </button>
                                                                )}

                                                                {rule && expanded === cellKey && (
                                                                    <div className="bg-card ring-border/60 mt-1 w-56 rounded-lg p-2 ring-1">
                                                                        <label className="flex items-center gap-1.5 text-[11px] font-medium">
                                                                            <input
                                                                                type="checkbox"
                                                                                checked={rule.requires_comment}
                                                                                onChange={(e) =>
                                                                                    patchRule(from, to.key, {
                                                                                        requires_comment: e.target.checked,
                                                                                    })
                                                                                }
                                                                                className="accent-primary size-3.5"
                                                                            />
                                                                            Require a written reason
                                                                        </label>

                                                                        {rule.requires_comment && (
                                                                            <Input
                                                                                value={rule.comment_label ?? ''}
                                                                                onChange={(e) =>
                                                                                    patchRule(from, to.key, {
                                                                                        comment_label: e.target.value,
                                                                                    })
                                                                                }
                                                                                placeholder="Prompt shown to the user"
                                                                                className="mt-1.5 h-8 text-[11px]"
                                                                            />
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                            </div>
                                        </div>
                                    );
                                })}
                            </SoftCardBody>
                        </SoftCard>
                    </div>

                    {/* -------------------------------------------- projects */}
                    <aside className="space-y-5">
                        <ProjectAssignment workflowId={workflow.id} projects={projects} />

                        <SubjectAssignment
                            eyebrow="Audience"
                            title="People"
                            empty="No active people yet."
                            note="Someone on this chain is only offered the stages above. Untick them to hand back the project's full chain."
                            subjects={people.map((p) => ({
                                id: p.id,
                                name: p.name,
                                detail: p.job_title || p.email,
                                assigned: p.assigned,
                                other_chain: p.other_chain,
                            }))}
                            onSave={(ids, done) =>
                                router.post(
                                    route('workflows.people.assign', workflow.id),
                                    { user_ids: ids },
                                    { preserveScroll: true, onFinish: done },
                                )
                            }
                        />

                        <SubjectAssignment
                            eyebrow="Audience"
                            title="Teams"
                            empty="No teams yet."
                            note="A team chain applies to every member who has no chain of their own."
                            subjects={teams.map((t) => ({
                                id: t.id,
                                name: t.name,
                                detail: t.slug,
                                assigned: t.assigned,
                                other_chain: t.other_chain,
                            }))}
                            onSave={(ids, done) =>
                                router.post(route('workflows.teams.assign', workflow.id), { team_ids: ids }, { preserveScroll: true, onFinish: done })
                            }
                        />

                        <SoftCard>
                            <SoftCardTitle eyebrow="Reference">Stage categories</SoftCardTitle>
                            <SoftCardBody className="space-y-2 text-xs">
                                {categories.map((c) => (
                                    <div key={c}>
                                        <p className="font-semibold">{CATEGORY_LABEL[c] ?? c}</p>
                                        <p className="text-muted-foreground">
                                            {c === 'done'
                                                ? 'Reaching this stage marks the task complete and stops overdue alerts.'
                                                : c === 'todo'
                                                  ? 'Work that has not started. One stage must be the starting point.'
                                                  : 'Work in flight. Counts toward open workload.'}
                                        </p>
                                    </div>
                                ))}
                            </SoftCardBody>
                        </SoftCard>
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete !== null}
                onOpenChange={(open) => !open && setConfirmDelete(null)}
                tone="destructive"
                title={`Remove “${confirmDelete?.name}”?`}
                description={
                    (confirmDelete?.task_count ?? 0) > 0
                        ? `${confirmDelete?.task_count} task(s) are currently in this stage. Move them to another stage first — the server will refuse otherwise.`
                        : 'This stage will be removed from the workflow, along with any transition rules referencing it.'
                }
                confirmLabel="Remove stage"
                onConfirm={() => {
                    if (confirmDelete) {
                        router.delete(route('workflows.statuses.destroy', [workflow.id, confirmDelete.id]), {
                            preserveScroll: true,
                        });
                    }
                    setConfirmDelete(null);
                }}
            />
        </AppLayout>
    );
}

function StatusEditor({
    status,
    workflowId,
    categories,
    colors,
    isFirst,
    isLast,
    onMoveUp,
    onMoveDown,
    onDelete,
}: {
    status: StatusRow;
    workflowId: number;
    categories: string[];
    colors: string[];
    isFirst: boolean;
    isLast: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onDelete: () => void;
}) {
    const [name, setName] = useState(status.name);
    const [category, setCategory] = useState(status.category);
    const [color, setColor] = useState(status.color);

    const changed = name !== status.name || category !== status.category || color !== status.color;

    const save = (extra: Record<string, unknown> = {}) => {
        router.patch(route('workflows.statuses.update', [workflowId, status.id]), { name, category, color, ...extra }, { preserveScroll: true });
    };

    return (
        <div className="bg-muted/30 ring-border/60 flex flex-wrap items-center gap-2 rounded-xl p-2.5 ring-1">
            <div className="flex flex-col">
                <button type="button" onClick={onMoveUp} disabled={isFirst} aria-label="Move up" className="disabled:opacity-30">
                    <ChevronUp className="size-3.5" />
                </button>
                <button type="button" onClick={onMoveDown} disabled={isLast} aria-label="Move down" className="disabled:opacity-30">
                    <ChevronDown className="size-3.5" />
                </button>
            </div>

            <span className={cn('size-2.5 shrink-0 rounded-full', statusDot(color))} />

            <Input value={name} onChange={(e) => setName(e.target.value)} className="h-8 w-40 text-xs" />

            <Select value={category} onValueChange={setCategory}>
                <SelectTrigger className="h-8 w-32 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {categories.map((c) => (
                        <SelectItem key={c} value={c}>
                            {CATEGORY_LABEL[c] ?? c}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={color} onValueChange={setColor}>
                <SelectTrigger className="h-8 w-24 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {colors.map((c) => (
                        <SelectItem key={c} value={c}>
                            {c}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* The key is what tasks store, so it is shown but never editable. */}
            <code className="text-muted-foreground font-mono text-[10px]">{status.key}</code>

            {status.is_initial ? (
                <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                    <Flag className="size-2.5" /> Start
                </span>
            ) : (
                <button
                    type="button"
                    onClick={() => save({ is_initial: true })}
                    className="text-muted-foreground hover:text-foreground text-[10px] underline underline-offset-2"
                >
                    Make start
                </button>
            )}

            {status.task_count > 0 && <span className="text-muted-foreground text-[10px] tabular-nums">{status.task_count} task(s)</span>}

            <div className="ml-auto flex items-center gap-1">
                {changed && (
                    <Button size="sm" variant="soft" onClick={() => save()}>
                        Save
                    </Button>
                )}
                <button type="button" onClick={onDelete} aria-label={`Remove ${status.name}`} className="text-muted-foreground hover:text-red-600">
                    <Trash2 className="size-3.5" />
                </button>
            </div>
        </div>
    );
}

function AddStatus({ workflowId, categories, colors }: { workflowId: number; categories: string[]; colors: string[] }) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [category, setCategory] = useState('in_progress');
    const [color, setColor] = useState('slate');

    const add = () => {
        if (!name.trim()) return;
        router.post(
            route('workflows.statuses.store', workflowId),
            { name: name.trim(), category, color },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setOpen(false);
                },
            },
        );
    };

    if (!open) {
        return (
            <Button size="sm" variant="soft" onClick={() => setOpen(true)} className="w-full">
                <Plus className="size-3.5" /> Add stage
            </Button>
        );
    }

    return (
        <div className="ring-border/60 flex flex-wrap items-center gap-2 rounded-xl p-2.5 ring-1">
            <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Stage name"
                autoFocus
                className="h-8 w-40 text-xs"
                onKeyDown={(e) => e.key === 'Enter' && add()}
            />
            <Select value={category} onValueChange={setCategory}>
                <SelectTrigger className="h-8 w-32 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {categories.map((c) => (
                        <SelectItem key={c} value={c}>
                            {CATEGORY_LABEL[c] ?? c}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={color} onValueChange={setColor}>
                <SelectTrigger className="h-8 w-24 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {colors.map((c) => (
                        <SelectItem key={c} value={c}>
                            {c}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button size="sm" onClick={add} disabled={!name.trim()}>
                Add
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>
                Cancel
            </Button>
        </div>
    );
}

function ProjectAssignment({ workflowId, projects }: { workflowId: number; projects: ProjectRow[] }) {
    const [selected, setSelected] = useState<number[]>(projects.filter((p) => p.assigned).map((p) => p.id));
    const [saving, setSaving] = useState(false);

    const toggle = (id: number) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const save = () => {
        setSaving(true);
        router.post(
            route('workflows.projects.assign', workflowId),
            { project_ids: selected },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <SoftCard>
            <SoftCardTitle
                eyebrow="Usage"
                action={
                    <Button size="sm" variant="soft" onClick={save} disabled={saving}>
                        {saving ? 'Saving…' : 'Apply'}
                    </Button>
                }
            >
                Projects
            </SoftCardTitle>
            <SoftCardBody className="space-y-1.5">
                {projects.length === 0 && <p className="text-muted-foreground text-xs">No projects yet.</p>}

                {projects.map((p) => (
                    <label key={p.id} className="hover:bg-muted/40 flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-xs">
                        <input type="checkbox" checked={selected.includes(p.id)} onChange={() => toggle(p.id)} className="accent-primary size-3.5" />
                        <span className="text-muted-foreground font-mono text-[10px]">{p.key}</span>
                        <span className="truncate">{p.title}</span>
                    </label>
                ))}

                <p className="text-muted-foreground pt-1 text-[10px]">
                    Unticking a project does not move it — assign it to another workflow instead.
                </p>
            </SoftCardBody>
        </SoftCard>
    );
}

/**
 * Narrows a set of people or teams to this chain.
 *
 * One component rather than two: people and teams differ only in the line
 * under the name and the field the endpoint expects. A subject already on
 * another chain says so on its row, because ticking it moves it — a subject
 * follows exactly one chain.
 */
function SubjectAssignment({
    eyebrow,
    title,
    empty,
    note,
    subjects,
    onSave,
}: {
    eyebrow: string;
    title: string;
    empty: string;
    note: string;
    subjects: SubjectRow[];
    onSave: (ids: number[], done: () => void) => void;
}) {
    const [selected, setSelected] = useState<number[]>(subjects.filter((s) => s.assigned).map((s) => s.id));
    const [query, setQuery] = useState('');
    const [saving, setSaving] = useState(false);

    const toggle = (id: number) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    // Ticked rows stay visible while filtering, so nobody is quietly dropped
    // from the set by a search term they happen not to match.
    const visible = useMemo(() => {
        const term = query.trim().toLowerCase();
        if (!term) return subjects;

        return subjects.filter((s) => selected.includes(s.id) || `${s.name} ${s.detail ?? ''}`.toLowerCase().includes(term));
    }, [subjects, query, selected]);

    const save = () => {
        setSaving(true);
        onSave(selected, () => setSaving(false));
    };

    return (
        <SoftCard>
            <SoftCardTitle
                eyebrow={eyebrow}
                action={
                    <Button size="sm" variant="soft" onClick={save} disabled={saving}>
                        {saving ? 'Saving…' : 'Apply'}
                    </Button>
                }
            >
                {title}
                {selected.length > 0 && <span className="text-muted-foreground ml-1.5 text-xs font-normal">({selected.length})</span>}
            </SoftCardTitle>
            <SoftCardBody className="space-y-1.5">
                {subjects.length === 0 ? (
                    <p className="text-muted-foreground text-xs">{empty}</p>
                ) : (
                    <>
                        {subjects.length > 8 && (
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={`Filter ${title.toLowerCase()}…`}
                                className="mb-1 h-8 text-xs"
                            />
                        )}

                        <div className="scrollbar-soft max-h-64 space-y-1.5 overflow-y-auto">
                            {visible.map((s) => (
                                <label key={s.id} className="hover:bg-muted/40 flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-xs">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(s.id)}
                                        onChange={() => toggle(s.id)}
                                        className="accent-primary mt-0.5 size-3.5 shrink-0"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate">{s.name}</span>
                                        {s.detail && <span className="text-muted-foreground block truncate text-[10px]">{s.detail}</span>}
                                        {s.other_chain && !selected.includes(s.id) && (
                                            <span className="block truncate text-[10px] text-amber-600 dark:text-amber-500">
                                                on “{s.other_chain}”
                                            </span>
                                        )}
                                    </span>
                                </label>
                            ))}

                            {visible.length === 0 && <p className="text-muted-foreground px-2 text-xs">Nothing matches.</p>}
                        </div>
                    </>
                )}

                <p className="text-muted-foreground pt-1 text-[10px]">{note}</p>
            </SoftCardBody>
        </SoftCard>
    );
}
