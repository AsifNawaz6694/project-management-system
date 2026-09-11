export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
export type ProjectPriority = 'low' | 'medium' | 'high' | 'critical';
export type ProjectColor = 'violet' | 'blue' | 'emerald' | 'amber' | 'rose' | 'pink' | 'sky' | 'slate';

export const STATUS_META: Record<ProjectStatus, { label: string; chip: string; dot: string }> = {
    planning: {
        label: 'Planning',
        chip: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
        dot: 'bg-blue-500',
    },
    active: {
        label: 'Active',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        dot: 'bg-emerald-500',
    },
    on_hold: {
        label: 'On hold',
        chip: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        dot: 'bg-amber-500',
    },
    completed: {
        label: 'Completed',
        chip: 'bg-indigo-50 text-indigo-700 ring-indigo-200/70 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/30',
        dot: 'bg-indigo-600',
    },
    cancelled: {
        label: 'Cancelled',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
        dot: 'bg-slate-500',
    },
};

export const PRIORITY_META: Record<ProjectPriority, { label: string; chip: string }> = {
    low: {
        label: 'Low',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
    },
    medium: {
        label: 'Medium',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
    },
    high: {
        label: 'High',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    },
    critical: {
        label: 'Critical',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
    },
};

export const COLOR_GRADIENT: Record<ProjectColor, string> = {
    violet: 'from-indigo-500 to-indigo-700',
    blue: 'from-blue-500 to-blue-700',
    emerald: 'from-emerald-500 to-emerald-700',
    amber: 'from-amber-500 to-amber-600',
    rose: 'from-red-500 to-red-700',
    pink: 'from-pink-400 to-pink-600',
    sky: 'from-sky-500 to-sky-700',
    slate: 'from-slate-500 to-slate-700',
};

export const COLOR_DOT: Record<ProjectColor, string> = {
    violet: 'bg-indigo-600',
    blue: 'bg-blue-600',
    emerald: 'bg-emerald-600',
    amber: 'bg-amber-500',
    rose: 'bg-red-600',
    pink: 'bg-pink-500',
    sky: 'bg-sky-600',
    slate: 'bg-slate-500',
};

export function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function daysBetween(start?: string | null, end?: string | null): number | null {
    if (!start || !end) return null;
    const ms = new Date(end).getTime() - new Date(start).getTime();
    return Math.round(ms / (1000 * 60 * 60 * 24));
}
