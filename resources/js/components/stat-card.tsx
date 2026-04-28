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
    violet: { iconBg: 'bg-gradient-to-br from-violet-500 to-purple-600', iconText: 'text-white', bar: 'from-violet-500 via-fuchsia-500 to-purple-600', chip: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30', glow: 'rgba(124,58,237,0.45)' },
    emerald: { iconBg: 'bg-gradient-to-br from-emerald-500 to-teal-600', iconText: 'text-white', bar: 'from-emerald-500 via-green-500 to-teal-600', chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30', glow: 'rgba(16,185,129,0.45)' },
    amber: { iconBg: 'bg-gradient-to-br from-amber-400 to-orange-500', iconText: 'text-white', bar: 'from-amber-400 via-orange-400 to-rose-500', chip: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30', glow: 'rgba(245,158,11,0.45)' },
    rose: { iconBg: 'bg-gradient-to-br from-rose-500 to-pink-600', iconText: 'text-white', bar: 'from-rose-500 via-pink-500 to-fuchsia-600', chip: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30', glow: 'rgba(244,63,94,0.45)' },
    blue: { iconBg: 'bg-gradient-to-br from-blue-500 to-indigo-600', iconText: 'text-white', bar: 'from-blue-500 via-cyan-500 to-indigo-600', chip: 'bg-blue-50 text-blue-700 ring-blue-200/70 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30', glow: 'rgba(59,130,246,0.45)' },
    slate: { iconBg: 'bg-gradient-to-br from-slate-600 to-slate-800', iconText: 'text-white', bar: 'from-slate-500 to-slate-700', chip: 'bg-slate-100 text-slate-700 ring-slate-200/70 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30', glow: 'rgba(100,116,139,0.4)' },
    sky: { iconBg: 'bg-gradient-to-br from-sky-400 to-cyan-500', iconText: 'text-white', bar: 'from-sky-400 to-cyan-500', chip: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30', glow: 'rgba(14,165,233,0.45)' },
    pink: { iconBg: 'bg-gradient-to-br from-pink-500 to-fuchsia-600', iconText: 'text-white', bar: 'from-pink-500 via-fuchsia-500 to-purple-600', chip: 'bg-pink-50 text-pink-700 ring-pink-200/70 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-500/30', glow: 'rgba(236,72,153,0.45)' },
};

export function StatCard({ label, value, delta, trend, icon: Icon, accent = 'violet', className, sub, sparkline }: StatCardProps) {
    const a = ACCENTS[accent];
    const TrendIcon = trend === 'down' ? ArrowDownRight : ArrowUpRight;
    const numericValue = typeof value === 'number'
        ? value
        : (typeof value === 'string' && /^[\d,.]+$/.test(value.replace(/[\s%]/g, '')) ? Number(value.replace(/[^\d.-]/g, '')) : null);

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
                'group bg-card shadow-soft-sm hover:shadow-soft-xl ring-1 ring-border/60 hover:ring-foreground/20 relative overflow-hidden rounded-2xl p-5 transition-all duration-300',
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
            <div className={cn('absolute inset-x-0 top-0 h-1 bg-gradient-to-r animate-gradient', a.bar)} />
            <div className="shimmer-overlay opacity-0 transition-opacity duration-500 group-hover:opacity-100" />

            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.14em]">{label}</p>
                    <p className="font-display mt-2 text-4xl font-bold leading-none tracking-tight tabular-nums">
                        {numericValue !== null ? (
                            <AnimatedNumber value={numericValue} formatter={typeof value === 'string' && value.includes('%') ? (n) => `${n}%` : undefined} />
                        ) : (
                            <span className="animate-count">{value}</span>
                        )}
                    </p>
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
                    {sub && <p className="text-muted-foreground mt-2 line-clamp-2 text-xs">{sub}</p>}
                </div>
                {Icon && (
                    <div className={cn('relative flex size-12 items-center justify-center rounded-2xl shadow-soft-md ring-1 ring-white/30 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6', a.iconBg, a.iconText)}>
                        <Icon className="size-5" />
                        <span aria-hidden className="absolute inset-0 -z-10 rounded-2xl opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-hover:animate-pulse-ring" />
                        <span aria-hidden className="absolute -right-1 -top-1 size-2 rounded-full bg-white/90 animate-sparkle" />
                    </div>
                )}
            </div>

            {sparkline && sparkline.length > 1 && (
                <div className="relative mt-4 -mx-1 h-10 opacity-90">
                    <MiniSparkline points={sparkline} tone={accent === 'slate' ? 'violet' : (accent as 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'sky' | 'pink')} />
                </div>
            )}
        </div>
    );
}
