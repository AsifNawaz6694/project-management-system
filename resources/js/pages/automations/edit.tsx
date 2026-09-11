import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { TRIGGER_LABEL } from '@/lib/automations';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ConditionRow {
    field: string;
    operator: string;
    value: string | null;
}

interface ActionRow {
    type: string;
    config: Record<string, string>;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    rule: {
        id: number;
        name: string;
        description: string | null;
        trigger: string;
        project_id: number | null;
        is_active: boolean;
        run_order: number;
        run_count: number;
        last_run_at: string | null;
    };
    conditions: ConditionRow[];
    actions: ActionRow[];
    triggers: string[];
    fields: string[];
    operators: string[];
    unaryOperators: string[];
    actionTypes: string[];
    priorities: string[];
    statuses: Option[];
    projects: Option[];
    users: Option[];
    labels: string[];
    runs: Array<{ id: number; task_id: number | null; status: string; message: string | null; created_at: string | null }>;
}

const FIELD_LABEL: Record<string, string> = {
    status: 'Current stage',
    priority: 'Priority',
    assignee_id: 'Assignee',
    team_id: 'Team',
    project_id: 'Project',
    task_type_id: 'Task type',
    title: 'Title',
    label: 'Label',
    is_overdue: 'Is overdue',
    from_status: 'Moved from stage',
    to_status: 'Moved to stage',
    changed_field: 'Field that changed',
    comment_body: 'Comment text',
    actor_id: 'Who did it',
};

const OPERATOR_LABEL: Record<string, string> = {
    equals: 'is',
    not_equals: 'is not',
    in: 'is one of',
    not_in: 'is none of',
    contains: 'contains',
    not_contains: 'does not contain',
    is_empty: 'is empty',
    is_not_empty: 'is not empty',
};

const ACTION_LABEL: Record<string, string> = {
    set_status: 'Move to stage',
    set_priority: 'Set priority',
    assign: 'Assign to',
    add_label: 'Add label',
    add_comment: 'Post a comment',
    add_watcher: 'Add watcher',
    set_due_date: 'Set due date',
    notify: 'Send a notification',
};

const TARGETS: Option[] = [
    { value: 'assignee', label: 'The assignee' },
    { value: 'reporter', label: 'The reporter' },
    { value: 'project_owner', label: 'The project owner' },
    { value: 'actor', label: 'Whoever triggered it' },
    { value: 'watchers', label: 'All watchers' },
];

const selectClass = 'border-border/60 bg-background h-9 rounded-md border px-2 text-xs';

export default function AutomationEdit({
    rule,
    conditions: initialConditions,
    actions: initialActions,
    triggers,
    fields,
    operators,
    unaryOperators,
    actionTypes,
    priorities,
    statuses,
    projects,
    users,
    labels,
    runs,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Automation', href: '/automations' },
        { title: rule.name, href: `/automations/${rule.id}/edit` },
    ];

    const [conditions, setConditions] = useState<ConditionRow[]>(initialConditions);
    const [actions, setActions] = useState<ActionRow[]>(initialActions);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [name, setName] = useState(rule.name);
    const [description, setDescription] = useState(rule.description ?? '');
    const [trigger, setTrigger] = useState(rule.trigger);
    const [projectId, setProjectId] = useState(rule.project_id ? String(rule.project_id) : '');

    const touch = () => setDirty(true);

    const addCondition = () => {
        setConditions((prev) => [...prev, { field: fields[0], operator: 'equals', value: '' }]);
        touch();
    };

    const patchCondition = (i: number, patch: Partial<ConditionRow>) => {
        setConditions((prev) => prev.map((c, idx) => (idx === i ? { ...c, ...patch } : c)));
        touch();
    };

    const addAction = () => {
        setActions((prev) => [...prev, { type: actionTypes[0], config: {} }]);
        touch();
    };

    const patchAction = (i: number, patch: Partial<ActionRow>) => {
        setActions((prev) => prev.map((a, idx) => (idx === i ? { ...a, ...patch } : a)));
        touch();
    };

    const patchConfig = (i: number, key: string, value: string) => {
        setActions((prev) => prev.map((a, idx) => (idx === i ? { ...a, config: { ...a.config, [key]: value } } : a)));
        touch();
    };

    const saveLogic = () => {
        setSaving(true);
        router.put(route('automations.logic.update', rule.id), { conditions, actions } as never, {
            preserveScroll: true,
            onSuccess: () => setDirty(false),
            onFinish: () => setSaving(false),
        });
    };

    const saveDetails = () => {
        router.patch(
            route('automations.update', rule.id),
            {
                name,
                description,
                trigger,
                project_id: projectId ? Number(projectId) : null,
                is_active: rule.is_active,
                run_order: rule.run_order,
            } as never,
            { preserveScroll: true },
        );
    };

    /** The value control changes shape with the field being compared. */
    const conditionValueOptions = (field: string): Option[] | null => {
        if (field === 'status' || field === 'from_status' || field === 'to_status') return statuses;
        if (field === 'priority') return priorities.map((p) => ({ value: p, label: p }));
        if (field === 'assignee_id' || field === 'actor_id') return users;
        if (field === 'project_id') return projects;
        if (field === 'label') return labels.map((l) => ({ value: l, label: l }));
        if (field === 'is_overdue')
            return [
                { value: '1', label: 'Yes' },
                { value: '0', label: 'No' },
            ];
        return null;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${rule.name} — automation`} />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Automation rule"
                    title={rule.name}
                    description={
                        rule.is_active ? 'This rule is live.' : 'This rule is paused — switch it on from the rules list once it looks right.'
                    }
                    actions={
                        <Button size="sm" onClick={saveLogic} disabled={saving || !dirty}>
                            {dirty ? 'Save logic' : 'Saved'}
                        </Button>
                    }
                />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex flex-col gap-4">
                        <SoftCard>
                            <SoftCardTitle>When</SoftCardTitle>
                            <SoftCardBody className="pt-0">
                                <select
                                    value={trigger}
                                    onChange={(e) => {
                                        setTrigger(e.target.value);
                                        touch();
                                    }}
                                    className={cn(selectClass, 'w-full text-sm')}
                                >
                                    {triggers.map((t) => (
                                        <option key={t} value={t}>
                                            {TRIGGER_LABEL[t] ?? t}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Changing the trigger takes effect once you save the details panel.
                                </p>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle>If — all of these are true</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-2 pt-0">
                                {conditions.length === 0 && (
                                    <p className="text-muted-foreground text-xs">No conditions — the rule runs every time the trigger fires.</p>
                                )}

                                {conditions.map((condition, i) => {
                                    const unary = unaryOperators.includes(condition.operator);
                                    const options = conditionValueOptions(condition.field);

                                    return (
                                        <div key={i} className="bg-muted/30 flex flex-wrap items-center gap-2 rounded-lg p-2">
                                            <select
                                                value={condition.field}
                                                onChange={(e) => patchCondition(i, { field: e.target.value, value: '' })}
                                                className={selectClass}
                                            >
                                                {fields.map((f) => (
                                                    <option key={f} value={f}>
                                                        {FIELD_LABEL[f] ?? f}
                                                    </option>
                                                ))}
                                            </select>

                                            <select
                                                value={condition.operator}
                                                onChange={(e) => patchCondition(i, { operator: e.target.value })}
                                                className={selectClass}
                                            >
                                                {operators.map((o) => (
                                                    <option key={o} value={o}>
                                                        {OPERATOR_LABEL[o] ?? o}
                                                    </option>
                                                ))}
                                            </select>

                                            {!unary &&
                                                (options ? (
                                                    <select
                                                        value={condition.value ?? ''}
                                                        onChange={(e) => patchCondition(i, { value: e.target.value })}
                                                        className={cn(selectClass, 'max-w-48')}
                                                    >
                                                        <option value="">Choose…</option>
                                                        {options.map((o) => (
                                                            <option key={o.value} value={o.value}>
                                                                {o.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <Input
                                                        value={condition.value ?? ''}
                                                        onChange={(e) => patchCondition(i, { value: e.target.value })}
                                                        placeholder="Value"
                                                        className="h-9 max-w-48 text-xs"
                                                    />
                                                ))}

                                            <button
                                                type="button"
                                                aria-label="Remove condition"
                                                onClick={() => {
                                                    setConditions((prev) => prev.filter((_, idx) => idx !== i));
                                                    touch();
                                                }}
                                                className="hover:bg-destructive/10 hover:text-destructive ml-auto rounded-md p-1.5 transition-colors"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </div>
                                    );
                                })}

                                <Button size="sm" variant="ghost" className="self-start text-xs" onClick={addCondition}>
                                    <Plus className="size-3" />
                                    Add condition
                                </Button>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle>Then — do this, in order</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-2 pt-0">
                                {actions.length === 0 && (
                                    <p className="text-muted-foreground text-xs">Add at least one action or the rule does nothing.</p>
                                )}

                                {actions.map((action, i) => (
                                    <div key={i} className="bg-muted/30 flex flex-wrap items-center gap-2 rounded-lg p-2">
                                        <select
                                            value={action.type}
                                            onChange={(e) => patchAction(i, { type: e.target.value, config: {} })}
                                            className={selectClass}
                                        >
                                            {actionTypes.map((t) => (
                                                <option key={t} value={t}>
                                                    {ACTION_LABEL[t] ?? t}
                                                </option>
                                            ))}
                                        </select>

                                        {action.type === 'set_status' && (
                                            <select
                                                value={action.config.status ?? ''}
                                                onChange={(e) => patchConfig(i, 'status', e.target.value)}
                                                className={cn(selectClass, 'max-w-48')}
                                            >
                                                <option value="">Choose a stage…</option>
                                                {statuses.map((s) => (
                                                    <option key={s.value} value={s.value}>
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </select>
                                        )}

                                        {action.type === 'set_priority' && (
                                            <select
                                                value={action.config.priority ?? ''}
                                                onChange={(e) => patchConfig(i, 'priority', e.target.value)}
                                                className={selectClass}
                                            >
                                                <option value="">Choose…</option>
                                                {priorities.map((p) => (
                                                    <option key={p} value={p}>
                                                        {p}
                                                    </option>
                                                ))}
                                            </select>
                                        )}

                                        {(action.type === 'assign' || action.type === 'add_watcher' || action.type === 'notify') && (
                                            <>
                                                <select
                                                    value={action.config.target ?? ''}
                                                    onChange={(e) => patchConfig(i, 'target', e.target.value)}
                                                    className={selectClass}
                                                >
                                                    <option value="">A specific person…</option>
                                                    {TARGETS.filter((t) => t.value !== 'watchers' || action.type === 'notify').map((t) => (
                                                        <option key={t.value} value={t.value}>
                                                            {t.label}
                                                        </option>
                                                    ))}
                                                </select>

                                                {!action.config.target && (
                                                    <select
                                                        value={action.config.user_id ?? ''}
                                                        onChange={(e) => patchConfig(i, 'user_id', e.target.value)}
                                                        className={cn(selectClass, 'max-w-48')}
                                                    >
                                                        <option value="">Choose a person…</option>
                                                        {users.map((u) => (
                                                            <option key={u.value} value={u.value}>
                                                                {u.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                            </>
                                        )}

                                        {action.type === 'add_label' && (
                                            <Input
                                                value={action.config.label ?? ''}
                                                onChange={(e) => patchConfig(i, 'label', e.target.value)}
                                                placeholder="Label name"
                                                className="h-9 max-w-48 text-xs"
                                            />
                                        )}

                                        {action.type === 'set_due_date' && (
                                            <Input
                                                type="number"
                                                value={action.config.days ?? ''}
                                                onChange={(e) => patchConfig(i, 'days', e.target.value)}
                                                placeholder="Days from now"
                                                className="h-9 max-w-40 text-xs"
                                            />
                                        )}

                                        {(action.type === 'add_comment' || action.type === 'notify') && (
                                            <Input
                                                value={action.config.body ?? ''}
                                                onChange={(e) => patchConfig(i, 'body', e.target.value)}
                                                placeholder="Message — {{task.key}}, {{task.title}} work here"
                                                className="h-9 min-w-56 flex-1 text-xs"
                                            />
                                        )}

                                        <button
                                            type="button"
                                            aria-label="Remove action"
                                            onClick={() => {
                                                setActions((prev) => prev.filter((_, idx) => idx !== i));
                                                touch();
                                            }}
                                            className="hover:bg-destructive/10 hover:text-destructive ml-auto rounded-md p-1.5 transition-colors"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </div>
                                ))}

                                <Button size="sm" variant="ghost" className="self-start text-xs" onClick={addAction}>
                                    <Plus className="size-3" />
                                    Add action
                                </Button>
                            </SoftCardBody>
                        </SoftCard>
                    </div>

                    <div className="flex flex-col gap-4">
                        <SoftCard>
                            <SoftCardTitle>Details</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-2 pt-0">
                                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Name" />
                                <Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Description" />
                                <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className={cn(selectClass, 'h-10 text-sm')}>
                                    <option value="">Every project</option>
                                    {projects.map((p) => (
                                        <option key={p.value} value={p.value}>
                                            {p.label}
                                        </option>
                                    ))}
                                </select>
                                <Button size="sm" variant="outline" onClick={saveDetails} className="self-start">
                                    Save details
                                </Button>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle>Recent runs</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-1.5 pt-0">
                                {runs.length === 0 && <p className="text-muted-foreground text-xs">This rule has not run yet.</p>}
                                {runs.map((run) => (
                                    <div key={run.id} className="flex items-start gap-2 text-xs">
                                        <span
                                            className={cn(
                                                'mt-1.5 size-1.5 shrink-0 rounded-full',
                                                run.status === 'success' ? 'bg-emerald-500' : run.status === 'failed' ? 'bg-red-500' : 'bg-slate-300',
                                            )}
                                        />
                                        <div className="min-w-0">
                                            <p className="truncate">
                                                {run.status}
                                                {run.task_id ? ` · task #${run.task_id}` : ''}
                                            </p>
                                            {run.message && <p className="text-muted-foreground truncate">{run.message}</p>}
                                        </div>
                                    </div>
                                ))}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle>Danger zone</SoftCardTitle>
                            <SoftCardBody className="pt-0">
                                <Button size="sm" variant="destructive" onClick={() => router.delete(route('automations.destroy', rule.id))}>
                                    <Trash2 className="size-3.5" />
                                    Delete rule
                                </Button>
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
