import { cn } from '@/lib/utils';
import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-react';

interface StatCardProps {
    label: string;
    value: string | number;
    delta?: string;
    trend?: 'up' | 'down' | 'flat';
    icon?: LucideIcon;
    accent?: 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'slate' | 'sky' | 'pink';
    className?: string;
    sub?: string;
}

const ACCENTS: Record<NonNullable<StatCardProps['accent']>, { iconBg: string; iconText: string; bar: string; chip: string }> = {
    violet: {
        iconBg: 'bg-gradient-to-br from-violet-500 to-purple-600',
        iconText: 'text-white',
        bar: 'from-violet-500 to-purple-600',
        chip: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
    },
    emerald: {
        iconBg: 'bg-gradient-to-br from-emerald-500 to-teal-600',
        iconText: 'text-white',
        bar: 'from-emerald-500 to-teal-600',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    },
    amber: {
        iconBg: 'bg-gradient-to-br from-amber-400 to-orange-500',
        iconText: 'text-white',
        bar: 'from-amber-400 to-orange-500',
        chip: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    },
    rose: {
        iconBg: 'bg-gradient-to-br from-rose-500 to-pink-600',
        iconText: 'text-white',
        bar: 'from-rose-500 to-pink-600',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
    },
    blue: {
        iconBg: 'bg-gradient-to-br from-blue-500 to-indigo-600',
        iconText: 'text-white',
        bar: 'from-blue-500 to-indigo-600',
        chip: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
    },
    slate: {
        iconBg: 'bg-gradient-to-br from-slate-600 to-slate-800',
        iconText: 'text-white',
        bar: 'from-slate-500 to-slate-700',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
    },
    sky: {
        iconBg: 'bg-gradient-to-br from-sky-400 to-cyan-500',
        iconText: 'text-white',
        bar: 'from-sky-400 to-cyan-500',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
    },
    pink: {
        iconBg: 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
        iconText: 'text-white',
        bar: 'from-pink-500 to-fuchsia-600',
        chip: 'bg-pink-50 text-pink-700 ring-pink-200/70 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-500/30',
    },
};

export function StatCard({ label, value, delta, trend, icon: Icon, accent = 'violet', className, sub }: StatCardProps) {
    const a = ACCENTS[accent];
    const TrendIcon = trend === 'down' ? ArrowDownRight : ArrowUpRight;

    return (
        <div
            className={cn(
                'group bg-card shadow-soft-sm hover:shadow-soft-lg ring-1 ring-border/60 relative overflow-hidden rounded-2xl p-5 transition-all duration-300 hover:-translate-y-0.5',
                className,
            )}
        >
            <div className={cn('absolute inset-x-0 top-0 h-1 bg-gradient-to-r', a.bar)} />
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.14em]">{label}</p>
                    <p className="font-display mt-2 text-3xl font-bold tracking-tight tabular-nums">{value}</p>
                    {delta && (
                        <span
                            className={cn(
                                'mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                trend === 'up' && 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
                                trend === 'down' && 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
                                (trend === 'flat' || !trend) && a.chip,
                            )}
                        >
                            {(trend === 'up' || trend === 'down') && <TrendIcon className="size-3" />}
                            {delta}
                        </span>
                    )}
                    {sub && <p className="text-muted-foreground mt-2 text-xs">{sub}</p>}
                </div>
                {Icon && (
                    <div className={cn('flex size-12 items-center justify-center rounded-2xl shadow-soft-md ring-1 ring-white/30', a.iconBg, a.iconText)}>
                        <Icon className="size-5" />
                    </div>
                )}
            </div>
        </div>
    );
}
