import { cn } from '@/lib/utils';

interface Slice {
    key: string;
    label: string;
    value: number;
    tone: string;
}

interface DonutChartProps {
    slices: Slice[];
    size?: number;
    thickness?: number;
    centerLabel?: string;
    centerValue?: string;
    className?: string;
}

const toneToColor: Record<string, string> = {
    violet: '#4a3aa7',
    blue: '#2a78d6',
    emerald: '#1baf7a',
    amber: '#eda100',
    rose: '#e34948',
    pink: '#e87ba4',
    sky: '#5598e7',
    slate: '#64748b',
};

export function DonutChart({ slices, size = 180, thickness = 22, centerLabel, centerValue, className }: DonutChartProps) {
    const total = slices.reduce((sum, s) => sum + s.value, 0) || 1;
    const radius = (size - thickness) / 2;
    const circumference = 2 * Math.PI * radius;

    let cumulative = 0;
    const segments = slices.map((s) => {
        const fraction = s.value / total;
        const offset = -cumulative * circumference;
        cumulative += fraction;
        return {
            ...s,
            length: fraction * circumference,
            offset,
        };
    });

    return (
        <div className={cn('flex items-center gap-6', className)}>
            <div className="relative shrink-0" style={{ width: size, height: size }}>
                <svg viewBox={`0 0 ${size} ${size}`} className="-rotate-90 transform">
                    <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth={thickness} className="stroke-muted" />
                    {segments.map((s, i) => (
                        <circle
                            key={s.key}
                            cx={size / 2}
                            cy={size / 2}
                            r={radius}
                            fill="none"
                            stroke={toneToColor[s.tone] ?? '#2a78d6'}
                            strokeWidth={thickness}
                            strokeDasharray={`${s.length} ${circumference}`}
                            strokeDashoffset={s.offset + s.length}
                            strokeLinecap="round"
                            style={{
                                animation: `dash-grow-${i} 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards`,
                                animationDelay: `${150 + i * 110}ms`,
                            }}
                        >
                            <style>{`
                                @keyframes dash-grow-${i} {
                                    from { stroke-dashoffset: ${s.offset + s.length}; opacity: 0.4; }
                                    to   { stroke-dashoffset: ${s.offset}; opacity: 1; }
                                }
                            `}</style>
                        </circle>
                    ))}
                </svg>
                <div
                    className="animate-pop-in absolute inset-0 flex flex-col items-center justify-center text-center"
                    style={{ animationDelay: '600ms' }}
                >
                    {centerValue && <span className="font-display text-2xl font-bold tabular-nums">{centerValue}</span>}
                    {centerLabel && (
                        <span className="text-muted-foreground mt-0.5 text-[10px] font-bold tracking-[0.14em] uppercase">{centerLabel}</span>
                    )}
                </div>
            </div>
            <ul className="flex-1 space-y-1.5 text-xs">
                {slices.map((s, i) => (
                    <li key={s.key} className="animate-fade-right flex items-center gap-2" style={{ animationDelay: `${700 + i * 70}ms` }}>
                        <span className="size-2.5 rounded-full" style={{ backgroundColor: toneToColor[s.tone] ?? '#2a78d6' }} />
                        <span className="text-foreground/80 flex-1 truncate">{s.label}</span>
                        <span className="font-semibold tabular-nums">{s.value}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
