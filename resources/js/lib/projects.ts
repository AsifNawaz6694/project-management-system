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
        chip: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
        dot: 'bg-violet-500',
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
    violet: 'from-violet-500 via-indigo-600 to-blue-600',
    blue: 'from-blue-500 via-sky-500 to-cyan-500',
    emerald: 'from-emerald-500 via-teal-500 to-cyan-600',
    amber: 'from-amber-400 via-orange-500 to-rose-500',
    rose: 'from-rose-500 via-pink-500 to-fuchsia-500',
    pink: 'from-pink-500 via-fuchsia-500 to-violet-500',
    sky: 'from-sky-400 via-cyan-500 to-blue-500',
    slate: 'from-slate-500 via-slate-700 to-slate-900',
};

export const COLOR_DOT: Record<ProjectColor, string> = {
    violet: 'bg-violet-500',
    blue: 'bg-blue-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    pink: 'bg-pink-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-500',
};

import { formatCurrency } from '@/lib/currency';

export function formatBudget(value?: number | string | null, currency?: string | null): string {
    return formatCurrency(value, currency);
}

export function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function daysBetween(start?: string | null, end?: string | null): number | null {
    if (!start || !end) return null;
    const ms = new Date(end).getTime() - new Date(start).getTime();
    return Math.round(ms / (1000 * 60 * 60 * 24));
}
