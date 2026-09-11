import { AnimatedNumber } from '@/components/animated-number';
import { MiniSparkline } from '@/components/mini-sparkline';
import { cn } from '@/lib/utils';
import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-react';
import { useRef, useState, type MouseEvent } from 'react';

interface StatCardProps {
    label: string;
    value: string | number;
    delta?: string;
    trend?: 'up' | 'down' | 'flat';
    icon?: LucideIcon;
    accent?: 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'slate' | 'sky' | 'pink';
    className?: string;
    sub?: string;
    sparkline?: number[];
}

const ACCENTS: Record<NonNullable<StatCardProps['accent']>, { iconBg: string; iconText: string; bar: string; chip: string; glow: string }> = {
    violet: {
        iconBg: 'bg-indigo-50 dark:bg-indigo-500/12',
        iconText: 'text-indigo-700 dark:text-indigo-300',
        bar: 'from-indigo-500 to-indigo-500',
        chip: 'bg-indigo-50 text-indigo-700 ring-indigo-200/70 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/30',
        glow: 'rgba(74,58,167,0.10)',
    },
    emerald: {
        iconBg: 'bg-emerald-50 dark:bg-emerald-500/12',
        iconText: 'text-emerald-700 dark:text-emerald-300',
        bar: 'from-emerald-500 to-emerald-500',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        glow: 'rgba(27,175,122,0.10)',
    },
    amber: {
        iconBg: 'bg-amber-50 dark:bg-amber-500/12',
        iconText: 'text-amber-700 dark:text-amber-300',
        bar: 'from-amber-500 to-amber-500',
        chip: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        glow: 'rgba(237,161,0,0.10)',
    },
    rose: {
        iconBg: 'bg-red-50 dark:bg-red-500/12',
        iconText: 'text-red-700 dark:text-red-300',
        bar: 'from-red-500 to-red-500',
        chip: 'bg-red-50 text-red-700 ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
        glow: 'rgba(227,73,72,0.10)',
    },
    blue: {
        iconBg: 'bg-blue-50 dark:bg-blue-500/12',
        iconText: 'text-blue-700 dark:text-blue-300',
        bar: 'from-blue-500 to-blue-500',
        chip: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
        glow: 'rgba(42,120,214,0.10)',
    },
    slate: {
        iconBg: 'bg-slate-100 dark:bg-slate-500/12',
        iconText: 'text-slate-700 dark:text-slate-300',
        bar: 'from-slate-400 to-slate-400',
        chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
        glow: 'rgba(100,116,139,0.10)',
    },
    sky: {
        iconBg: 'bg-sky-50 dark:bg-sky-500/12',
        iconText: 'text-sky-700 dark:text-sky-300',
        bar: 'from-sky-500 to-sky-500',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
        glow: 'rgba(85,152,231,0.10)',
    },
    pink: {
        iconBg: 'bg-pink-50 dark:bg-pink-500/12',
        iconText: 'text-pink-700 dark:text-pink-300',
        bar: 'from-pink-400 to-pink-400',
        chip: 'bg-pink-50 text-pink-700 ring-pink-200/70 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-500/30',
        glow: 'rgba(232,123,164,0.10)',
    },
};

export function StatCard({ label, value, delta, trend, icon: Icon, accent = 'violet', className, sub, sparkline }: StatCardProps) {
    const a = ACCENTS[accent];
    const TrendIcon = trend === 'down' ? ArrowDownRight : ArrowUpRight;
    const numericValue =
        typeof value === 'number'
            ? value
            : typeof value === 'string' && /^[\d,.]+$/.test(value.replace(/[\s%]/g, ''))
              ? Number(value.replace(/[^\d.-]/g, ''))
              : null;

    const cardRef = useRef<HTMLDivElement>(null);
    const [glow, setGlow] = useState({ x: 50, y: 50, on: false });

    const onMove = (e: MouseEvent<HTMLDivElement>) => {
        const rect = cardRef.current?.getBoundingClientRect();
        if (!rect) return;
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        setGlow({ x, y, on: true });
    };
    const onLeave = () => setGlow((g) => ({ ...g, on: false }));

    return (
        <div
            ref={cardRef}
            onMouseMove={onMove}
            onMouseLeave={onLeave}
            className={cn(
                'group bg-card shadow-soft-sm hover:shadow-soft-xl ring-border/60 hover:ring-foreground/20 relative overflow-hidden rounded-2xl p-5 ring-1 transition-all duration-300',
                className,
            )}
            style={{
                transform: glow.on ? 'translateY(-4px)' : 'translateY(0)',
            }}
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                style={{ background: `radial-gradient(circle 240px at ${glow.x}% ${glow.y}%, ${a.glow}, transparent 60%)` }}
            />
            <div className={cn('absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r', a.bar)} />

            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-[11px] font-bold tracking-[0.14em] uppercase">{label}</p>
                    <p className="font-display mt-2 text-3xl leading-none font-bold tracking-tight tabular-nums">
                        {numericValue !== null ? (
                            <AnimatedNumber
                                value={numericValue}
                                formatter={typeof value === 'string' && value.includes('%') ? (n) => `${n}%` : undefined}
                            />
                        ) : (
                            <span className="animate-count">{value}</span>
                        )}
                    </p>
                    {delta && (
                        <span
                            className={cn(
                                'mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                trend === 'up' &&
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
                                trend === 'down' &&
                                    'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
                                (trend === 'flat' || !trend) && a.chip,
                            )}
                        >
                            {(trend === 'up' || trend === 'down') && <TrendIcon className="size-3" />}
                            {delta}
                        </span>
                    )}
                    {sub && <p className="text-muted-foreground mt-2 line-clamp-2 text-xs">{sub}</p>}
                </div>
                {Icon && (
                    <div className={cn('flex size-11 items-center justify-center rounded-xl', a.iconBg, a.iconText)}>
                        <Icon className="size-5" />
                    </div>
                )}
            </div>

            {sparkline && sparkline.length > 1 && (
                <div className="relative -mx-1 mt-4 h-10 opacity-90">
                    <MiniSparkline
                        points={sparkline}
                        tone={accent === 'slate' ? 'violet' : (accent as 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'sky' | 'pink')}
                    />
                </div>
            )}
        </div>
    );
}
