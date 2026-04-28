import { cn } from '@/lib/utils';

interface SeriesPoint {
    label: string;
    [key: string]: string | number;
}

interface SeriesConfig {
    key: string;
    label: string;
    tone: string;
}

interface AreaChartProps {
    points: SeriesPoint[];
    series: SeriesConfig[];
    height?: number;
    className?: string;
}

const TONE_COLOR: Record<string, string> = {
    violet: '#7c3aed',
    blue: '#3b82f6',
    emerald: '#10b981',
    amber: '#f59e0b',
    rose: '#f43f5e',
    pink: '#ec4899',
};

export function AreaChart({ points, series, height = 180, className }: AreaChartProps) {
    if (points.length === 0) {
        return (
            <div className={cn('text-muted-foreground flex h-44 items-center justify-center text-xs', className)}>
                No data yet
            </div>
        );
    }

    const width = 600;
    const padding = { top: 16, right: 16, bottom: 28, left: 32 };
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;

    const allValues = points.flatMap((p) => series.map((s) => Number(p[s.key] ?? 0)));
    const maxRaw = Math.max(...allValues, 1);
    const maxVal = Math.ceil(maxRaw * 1.2);

    const xFor = (i: number) => padding.left + (i / Math.max(1, points.length - 1)) * innerW;
    const yFor = (v: number) => padding.top + innerH - (v / maxVal) * innerH;

    const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => ({ y: padding.top + innerH * (1 - t), value: Math.round(maxVal * t) }));

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className={cn('w-full', className)} preserveAspectRatio="none">
            <defs>
                {series.map((s) => (
                    <linearGradient key={s.key} id={`grad-${s.key}`} x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stopColor={TONE_COLOR[s.tone] ?? '#7c3aed'} stopOpacity="0.35" />
                        <stop offset="100%" stopColor={TONE_COLOR[s.tone] ?? '#7c3aed'} stopOpacity="0" />
                    </linearGradient>
                ))}
            </defs>
            {ticks.map((t, i) => (
                <g key={i}>
                    <line x1={padding.left} x2={width - padding.right} y1={t.y} y2={t.y} stroke="currentColor" strokeOpacity="0.06" />
                    <text x={padding.left - 6} y={t.y + 3} fontSize={9} textAnchor="end" fill="currentColor" opacity="0.4">
                        {t.value}
                    </text>
                </g>
            ))}

            {series.map((s, sIdx) => {
                const linePoints = points.map((p, i) => `${xFor(i)},${yFor(Number(p[s.key] ?? 0))}`);
                const areaPoints = [
                    `${xFor(0)},${padding.top + innerH}`,
                    ...linePoints,
                    `${xFor(points.length - 1)},${padding.top + innerH}`,
                ];
                const lineLength = innerW + innerH; // generous estimate
                return (
                    <g key={s.key}>
                        <polygon
                            points={areaPoints.join(' ')}
                            fill={`url(#grad-${s.key})`}
                            style={{
                                opacity: 0,
                                animation: `fade-in-up 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards`,
                                animationDelay: `${300 + sIdx * 120}ms`,
                            }}
                        />
                        <polyline
                            points={linePoints.join(' ')}
                            fill="none"
                            stroke={TONE_COLOR[s.tone] ?? '#7c3aed'}
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            style={{
                                strokeDasharray: lineLength,
                                strokeDashoffset: lineLength,
                                animation: `draw-stroke 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards`,
                                animationDelay: `${sIdx * 120}ms`,
                                ['--dash-len' as string]: lineLength,
                            }}
                        />
                        {points.map((p, i) => (
                            <circle
                                key={i}
                                cx={xFor(i)}
                                cy={yFor(Number(p[s.key] ?? 0))}
                                r={2.5}
                                fill={TONE_COLOR[s.tone] ?? '#7c3aed'}
                                style={{
                                    opacity: 0,
                                    animation: `pop-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards`,
                                    animationDelay: `${800 + sIdx * 120 + i * 30}ms`,
                                    transformOrigin: `${xFor(i)}px ${yFor(Number(p[s.key] ?? 0))}px`,
                                }}
                            >
                                <title>{`${p.label} · ${s.label}: ${p[s.key]}`}</title>
                            </circle>
                        ))}
                    </g>
                );
            })}

            {points.map((p, i) => (
                <text key={i} x={xFor(i)} y={height - 8} fontSize={9} textAnchor="middle" fill="currentColor" opacity="0.5">
                    {p.label}
                </text>
            ))}
        </svg>
    );
}
