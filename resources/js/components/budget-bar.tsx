import { formatMoney } from '@/lib/expenses';
import { cn } from '@/lib/utils';

interface BudgetBarProps {
    budget: number;
    spent: number;
    pending?: number;
    utilization: number;
    overBudget: boolean;
    compact?: boolean;
    currency?: string | null;
}

export function BudgetBar({ budget, spent, pending = 0, utilization, overBudget, compact, currency }: BudgetBarProps) {
    const remaining = Math.max(budget - spent, 0);
    const pendingPct = budget > 0 ? Math.min(100 - utilization, (pending / budget) * 100) : 0;

    return (
        <div className={cn('space-y-2', compact && 'space-y-1.5')}>
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground font-bold uppercase tracking-[0.14em]">Budget</span>
                <span className="font-bold tabular-nums">
                    {formatMoney(spent, currency)}{' '}
                    <span className="text-muted-foreground font-medium">/ {formatMoney(budget, currency)}</span>
                </span>
            </div>
            <div className={cn('bg-muted relative overflow-hidden rounded-full', compact ? 'h-1.5' : 'h-2.5')}>
                <div
                    className={cn(
                        'absolute inset-y-0 left-0 rounded-full bg-gradient-to-r transition-[width] duration-700',
                        overBudget ? 'from-rose-500 to-pink-600' : utilization > 80 ? 'from-amber-400 to-orange-500' : 'from-emerald-500 to-teal-600',
                    )}
                    style={{ width: `${Math.min(100, utilization)}%` }}
                />
                {pendingPct > 0 && !overBudget && (
                    <div
                        className="absolute inset-y-0 rounded-full bg-amber-300/70 dark:bg-amber-400/40"
                        style={{ left: `${utilization}%`, width: `${pendingPct}%` }}
                    />
                )}
            </div>
            <div className="text-muted-foreground flex items-center justify-between text-[11px]">
                <span>
                    {utilization}% used{overBudget && <span className="ml-1 font-bold text-rose-600 dark:text-rose-400">· over budget</span>}
                </span>
                <span>
                    Remaining: <span className="text-foreground font-semibold">{formatMoney(remaining, currency)}</span>
                </span>
            </div>
        </div>
    );
}
