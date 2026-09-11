export type TaskStatus = string;
export type TaskPriority = 'low' | 'medium' | 'high' | 'critical';
export type StatusCategory = 'todo' | 'in_progress' | 'done';

export interface WorkflowStatus {
    key: TaskStatus;
    name: string;
    category: StatusCategory;
    color: string;
    position: number;
    is_initial?: boolean;
}

export interface TaskLabel {
    id: number;
    name: string;
    slug: string;
    color: string;
}

export interface TaskTypeMeta {
    id: number;
    key: string;
    name: string;
    icon: string;
    color: string;
}

/**
 * Statuses are configurable per project now, so their presentation is derived
 * from the colour the workflow defines rather than a hardcoded map.
 */
const CHIP: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
    blue: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    rose: 'bg-red-50 text-red-700 ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
    violet: 'bg-indigo-50 text-indigo-700 ring-indigo-200/70 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/30',
    sky: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
    pink: 'bg-pink-50 text-pink-700 ring-pink-200/70 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-500/30',
};

const DOT: Record<string, string> = {
    slate: 'bg-slate-500',
    blue: 'bg-blue-600',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-600',
    rose: 'bg-red-600',
    violet: 'bg-indigo-600',
    sky: 'bg-sky-600',
    pink: 'bg-pink-500',
};

const BAR: Record<string, string> = {
    slate: 'from-slate-500 to-slate-700',
    blue: 'from-blue-500 to-blue-700',
    amber: 'from-amber-500 to-amber-600',
    emerald: 'from-emerald-500 to-emerald-700',
    rose: 'from-red-500 to-red-700',
    violet: 'from-indigo-500 to-indigo-700',
    sky: 'from-sky-500 to-sky-700',
    pink: 'from-pink-400 to-pink-600',
};

export function statusChip(color?: string): string {
    return CHIP[color ?? 'slate'] ?? CHIP.slate;
}

export function statusDot(color?: string): string {
    return DOT[color ?? 'slate'] ?? DOT.slate;
}

export function statusBar(color?: string): string {
    return BAR[color ?? 'slate'] ?? BAR.slate;
}

/**
 * Presentation for a status key when no workflow context is available — the
 * dashboard and reports aggregate across projects that may use different
 * workflows, so they render from this rather than from one project's set.
 */
const FALLBACK: Record<string, { name: string; color: string; category: StatusCategory }> = {
    todo: { name: 'To do', color: 'slate', category: 'todo' },
    in_progress: { name: 'In progress', color: 'amber', category: 'in_progress' },
    review: { name: 'Review', color: 'blue', category: 'in_progress' },
    deployed: { name: 'Deployed', color: 'emerald', category: 'done' },
    done: { name: 'Done', color: 'emerald', category: 'done' },
};

export function fallbackStatus(key: string): { name: string; color: string; category: StatusCategory } {
    return FALLBACK[key] ?? { name: key.replace(/_/g, ' '), color: 'slate', category: 'todo' };
}

/** Index a workflow status list by key for O(1) lookup during render. */
export function indexStatuses(statuses: WorkflowStatus[]): Record<string, WorkflowStatus> {
    return statuses.reduce<Record<string, WorkflowStatus>>((acc, s) => {
        acc[s.key] = s;
        return acc;
    }, {});
}

export function statusLabel(statuses: Record<string, WorkflowStatus>, key: string): string {
    return statuses[key]?.name ?? key.replace(/_/g, ' ');
}

export function isDoneStatus(statuses: Record<string, WorkflowStatus>, key: string): boolean {
    return statuses[key]?.category === 'done';
}

export const TASK_PRIORITY_META: Record<TaskPriority, { label: string; chip: string; dot: string; rank: number }> = {
    critical: {
        label: 'Critical',
        chip: 'bg-red-50 text-red-700 ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
        dot: 'bg-red-600',
        rank: 4,
    },
    high: {
        label: 'High',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        dot: 'bg-amber-500',
        rank: 3,
    },
    medium: {
        label: 'Medium',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
        dot: 'bg-sky-600',
        rank: 2,
    },
    low: {
        label: 'Low',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
        dot: 'bg-slate-500',
        rank: 1,
    },
};

export function isOverdue(dueDate?: string | null, completedAt?: string | null): boolean {
    if (!dueDate || completedAt) return false;
    const due = new Date(dueDate);
    due.setHours(23, 59, 59, 999);
    return due.getTime() < Date.now();
}

export function relativeDue(dueDate?: string | null): string {
    if (!dueDate) return 'No due date';

    const due = new Date(dueDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);

    const days = Math.round((due.getTime() - today.getTime()) / 86400000);

    if (days === 0) return 'Due today';
    if (days === 1) return 'Due tomorrow';
    if (days === -1) return '1 day overdue';
    if (days < 0) return `${Math.abs(days)} days overdue`;
    if (days < 7) return `Due in ${days} days`;

    return due.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export function formatMinutes(minutes?: number | null): string {
    if (!minutes) return '—';
    if (minutes < 60) return `${minutes}m`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
}
