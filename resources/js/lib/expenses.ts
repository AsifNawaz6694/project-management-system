export type ExpenseStatus = 'pending' | 'approved' | 'rejected';
export type ExpenseCategory =
    | 'travel'
    | 'meals'
    | 'supplies'
    | 'software'
    | 'services'
    | 'hardware'
    | 'subscriptions'
    | 'other';

export const EXPENSE_STATUS_META: Record<ExpenseStatus, { label: string; chip: string; dot: string }> = {
    pending: {
        label: 'Pending',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        dot: 'bg-amber-500',
    },
    approved: {
        label: 'Approved',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        dot: 'bg-emerald-500',
    },
    rejected: {
        label: 'Rejected',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
        dot: 'bg-rose-500',
    },
};

export const EXPENSE_CATEGORY_META: Record<ExpenseCategory, { label: string; chip: string }> = {
    travel: { label: 'Travel', chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300' },
    meals: { label: 'Meals', chip: 'bg-amber-50 text-amber-800 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300' },
    supplies: { label: 'Supplies', chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300' },
    software: { label: 'Software', chip: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300' },
    services: { label: 'Services', chip: 'bg-pink-50 text-pink-700 ring-pink-200/70 dark:bg-pink-500/10 dark:text-pink-300' },
    hardware: { label: 'Hardware', chip: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300' },
    subscriptions: { label: 'Subscriptions', chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300' },
    other: { label: 'Other', chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300' },
};

import { formatCurrency, DEFAULT_CURRENCY } from '@/lib/currency';

export function formatMoney(amount: number | string | null | undefined, currency: string | null | undefined = DEFAULT_CURRENCY): string {
    return formatCurrency(amount, currency);
}

export function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${sizes[i]}`;
}
