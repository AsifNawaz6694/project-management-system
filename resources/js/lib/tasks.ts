export type TaskStatus = 'todo' | 'in_progress' | 'completed';
export type TaskPriority = 'low' | 'medium' | 'high' | 'critical';

export const TASK_STATUS_META: Record<TaskStatus, { label: string; chip: string; column: string; dot: string; bar: string }> = {
    todo: {
        label: 'To do',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
        column: 'from-slate-500 to-slate-700',
        dot: 'bg-slate-500',
        bar: 'from-slate-500 to-slate-700',
    },
    in_progress: {
        label: 'In progress',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        column: 'from-amber-400 to-orange-500',
        dot: 'bg-amber-500',
        bar: 'from-amber-400 to-orange-500',
    },
    completed: {
        label: 'Completed',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        column: 'from-emerald-500 to-teal-600',
        dot: 'bg-emerald-500',
        bar: 'from-emerald-500 to-teal-600',
    },
};

export const TASK_PRIORITY_META: Record<TaskPriority, { label: string; chip: string; flag: string }> = {
    low: {
        label: 'Low',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
        flag: 'text-slate-500',
    },
    medium: {
        label: 'Medium',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
        flag: 'text-sky-500',
    },
    high: {
        label: 'High',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        flag: 'text-amber-500',
    },
    critical: {
        label: 'Critical',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
        flag: 'text-rose-500',
    },
};

export function isOverdue(due?: string | null, status?: TaskStatus): boolean {
    if (!due || status === 'completed') return false;
    return new Date(due).getTime() < Date.now() - 24 * 60 * 60 * 1000;
}

export function relativeDue(due?: string | null): string {
    if (!due) return '—';
    const target = new Date(due);
    const now = new Date();
    const diff = Math.round((target.getTime() - now.setHours(0, 0, 0, 0)) / (1000 * 60 * 60 * 24));
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Tomorrow';
    if (diff === -1) return 'Yesterday';
    if (diff > 1 && diff < 14) return `In ${diff}d`;
    if (diff < -1 && diff > -14) return `${-diff}d overdue`;
    return target.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
