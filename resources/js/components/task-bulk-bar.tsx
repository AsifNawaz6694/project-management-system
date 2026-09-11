import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TASK_PRIORITY_META, type TaskPriority, type WorkflowStatus } from '@/lib/tasks';
import { router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Option {
    id: number;
    name?: string;
    slug?: string;
}

interface TaskBulkBarProps {
    selected: number[];
    statuses: WorkflowStatus[];
    assignees: Array<{ id: number; name: string }>;
    teams: Option[];
    labels: Option[];
    priorities: TaskPriority[];
    /** Archive, restore and delete are a manager's; hidden otherwise. */
    canManageLifecycle?: boolean;
    onClear: () => void;
}

/**
 * Floating action bar for bulk operations. Every action posts the selected ids
 * to a single endpoint that re-authorises each task server-side.
 */
export function TaskBulkBar({ selected, statuses, assignees, teams, priorities, canManageLifecycle = false, onClear }: TaskBulkBarProps) {
    const [busy, setBusy] = useState(false);

    const run = (payload: Record<string, unknown>) => {
        setBusy(true);
        router.post(
            route('tasks.bulk'),
            { ids: selected, ...payload },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    onClear();
                },
            },
        );
    };

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
            <div className="bg-card ring-border shadow-soft-xl pointer-events-auto flex flex-wrap items-center gap-2 rounded-2xl p-2.5 ring-1">
                <span className="bg-primary text-primary-foreground rounded-lg px-2.5 py-1 text-xs font-bold tabular-nums">
                    {selected.length} selected
                </span>

                <Select onValueChange={(v) => run({ action: 'status', status: v })} disabled={busy}>
                    <SelectTrigger className="h-8 w-auto min-w-[120px] text-xs">
                        <SelectValue placeholder="Set status" />
                    </SelectTrigger>
                    <SelectContent>
                        {statuses.map((s) => (
                            <SelectItem key={s.key} value={s.key}>
                                {s.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select onValueChange={(v) => run({ action: 'assignee', assignee_id: v === 'none' ? null : Number(v) })} disabled={busy}>
                    <SelectTrigger className="h-8 w-auto min-w-[130px] text-xs">
                        <SelectValue placeholder="Assign to" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Unassign</SelectItem>
                        {assignees.map((a) => (
                            <SelectItem key={a.id} value={String(a.id)}>
                                {a.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select onValueChange={(v) => run({ action: 'team', team_id: v === 'none' ? null : Number(v) })} disabled={busy}>
                    <SelectTrigger className="h-8 w-auto min-w-[110px] text-xs">
                        <SelectValue placeholder="Team" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No team</SelectItem>
                        {teams.map((t) => (
                            <SelectItem key={t.id} value={String(t.id)}>
                                {t.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select onValueChange={(v) => run({ action: 'priority', priority: v })} disabled={busy}>
                    <SelectTrigger className="h-8 w-auto min-w-[110px] text-xs">
                        <SelectValue placeholder="Priority" />
                    </SelectTrigger>
                    <SelectContent>
                        {priorities.map((p) => (
                            <SelectItem key={p} value={p}>
                                {TASK_PRIORITY_META[p].label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {canManageLifecycle && (
                    <>
                        <Button size="sm" variant="soft" onClick={() => run({ action: 'archive' })} disabled={busy}>
                            <Archive className="size-3.5" /> Archive
                        </Button>
                        <Button size="sm" variant="soft" onClick={() => run({ action: 'unarchive' })} disabled={busy}>
                            <ArchiveRestore className="size-3.5" /> Restore
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => {
                                if (confirm(`Delete ${selected.length} task(s)? They can be restored from the trash.`)) {
                                    run({ action: 'delete' });
                                }
                            }}
                            disabled={busy}
                        >
                            <Trash2 className="size-3.5" /> Delete
                        </Button>
                    </>
                )}

                <Button size="sm" variant="ghost" onClick={onClear} disabled={busy} aria-label="Clear selection">
                    <X className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}
